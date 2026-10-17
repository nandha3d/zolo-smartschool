<?php

namespace App\Addons\Hostel\Services;

use App\Addons\Hostel\Models\HostelAllocation;
use App\Addons\Hostel\Models\HostelAttendance;
use App\Addons\Hostel\Models\HostelBed;
use App\Addons\Hostel\Models\HostelBlock;
use App\Addons\Hostel\Models\HostelFee;
use App\Addons\Hostel\Models\HostelGatePass;
use App\Addons\Hostel\Models\HostelRoom;
use App\Addons\Hostel\Models\HostelRoomType;
use App\Models\Students;
use App\Services\CachingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Residency rules: bed allocation (one active bed per student, block gender rules),
 * vacate / transfer, hostel fee dues, roll call with parent alerts, and gate passes
 * that need both the guardian and the warden before a student may leave.
 */
class HostelService
{
    public function createRoom(array $data): HostelRoom
    {
        return DB::transaction(function () use ($data) {
            $room = HostelRoom::create($data);
            $this->syncBeds($room);
            return $room;
        });
    }

    /** Make the room's beds match its capacity (101-A, 101-B …); never removes an occupied bed. */
    public function syncBeds(HostelRoom $room): void
    {
        $beds = $room->beds()->orderBy('id')->get();
        $capacity = max(1, (int) $room->bed_capacity);

        for ($i = $beds->count(); $i < $capacity; $i++) {
            HostelBed::create([
                'school_id' => $room->school_id,
                'room_id' => $room->id,
                'bed_number' => $room->room_number . '-' . $this->bedLetter($i),
            ]);
        }

        if ($beds->count() > $capacity) {
            $surplus = $beds->slice($capacity);
            if ($surplus->contains(fn($b) => $b->current_allocation_id)) {
                throw new RuntimeException('Some beds being removed are occupied. Vacate them first.');
            }
            HostelBed::whereIn('id', $surplus->pluck('id'))->delete();
        }
    }

    public function allocate(int $studentId, int $bedId, ?string $date = null, ?float $feeAmount = null, ?string $remarks = null): HostelAllocation
    {
        return DB::transaction(function () use ($studentId, $bedId, $date, $feeAmount, $remarks) {
            $student = Students::with('user')->findOrFail($studentId);
            $bed = HostelBed::with('room.block')->lockForUpdate()->findOrFail($bedId);

            if ($bed->current_allocation_id) {
                throw new RuntimeException('This bed is already occupied.');
            }
            if ($bed->room->status !== 'Available') {
                throw new RuntimeException('This room is under maintenance.');
            }
            if (HostelAllocation::where('student_id', $student->id)->where('status', HostelAllocation::ACTIVE)->lockForUpdate()->exists()) {
                throw new RuntimeException('The student already has a bed. Use transfer instead.');
            }
            $this->assertBlockAccepts($bed->room->block, $student);

            $allocation = HostelAllocation::create([
                'school_id' => $bed->school_id,
                'student_id' => $student->id,
                'user_id' => $student->user_id,
                'bed_id' => $bed->id,
                'session_year_id' => app(CachingService::class)->getDefaultSessionYear()?->id,
                'allocation_date' => $date ? Carbon::parse($date) : Carbon::today(),
                'status' => HostelAllocation::ACTIVE,
                'fee_amount' => $feeAmount,
                'remarks' => $remarks,
            ]);
            $bed->update(['current_allocation_id' => $allocation->id]);

            return $allocation;
        });
    }

    public function vacate(HostelAllocation $allocation, ?string $date = null, string $status = 'Vacated'): void
    {
        DB::transaction(function () use ($allocation, $date, $status) {
            $allocation = HostelAllocation::whereKey($allocation->id)->lockForUpdate()->firstOrFail();
            if ($allocation->status !== HostelAllocation::ACTIVE) {
                throw new RuntimeException('This allocation is already closed.');
            }
            $allocation->update(['status' => $status, 'vacate_date' => $date ? Carbon::parse($date) : Carbon::today()]);
            HostelBed::whereKey($allocation->bed_id)->where('current_allocation_id', $allocation->id)->update(['current_allocation_id' => null]);
        });
    }

    public function transfer(HostelAllocation $allocation, int $newBedId): HostelAllocation
    {
        return DB::transaction(function () use ($allocation, $newBedId) {
            $this->vacate($allocation, null, 'Transferred');
            return $this->allocate($allocation->student_id, $newBedId, null, $allocation->fee_amount ? (float) $allocation->fee_amount : null, 'Transferred from bed #' . $allocation->bed_id);
        });
    }

    /**
     * Raise one due per active allocation for the cycle that contains $periodStart.
     * Idempotent: an allocation already billed for that period is skipped.
     *
     * @return int number of dues created
     */
    public function generateFees(string $periodStart, int $dueInDays = 10): int
    {
        $start = Carbon::parse($periodStart)->startOfMonth();
        $created = 0;

        HostelAllocation::with('bed.room.roomType')->where('status', HostelAllocation::ACTIVE)->chunkById(200, function ($allocations) use ($start, $dueInDays, &$created) {
            foreach ($allocations as $allocation) {
                $type = $allocation->bed?->room?->roomType;
                $months = HostelRoomType::CYCLES[$type?->fee_cycle ?? 'Monthly'] ?? 1;
                $amount = $allocation->fee_amount !== null ? (float) $allocation->fee_amount : (float) ($type?->base_fee ?? 0);
                if ($amount <= 0) {
                    continue;
                }
                // Align quarterly / annual cycles to the start of their block of months.
                $periodStart = $start->copy()->subMonths(($start->month - 1) % $months);
                $periodEnd = $periodStart->copy()->addMonths($months)->subDay();
                if (Carbon::parse($allocation->allocation_date)->greaterThan($periodEnd)) {
                    continue;
                }
                $exists = HostelFee::where('allocation_id', $allocation->id)->whereDate('period_start', $periodStart)->exists();
                if ($exists) {
                    continue;
                }
                HostelFee::create([
                    'school_id' => $allocation->school_id,
                    'allocation_id' => $allocation->id,
                    'student_id' => $allocation->student_id,
                    'period_label' => $months === 1 ? $periodStart->format('M Y') : $periodStart->format('M Y') . ' – ' . $periodEnd->format('M Y'),
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'amount' => $amount,
                    'due_date' => $periodStart->copy()->addDays($dueInDays),
                    'status' => 'Unpaid',
                ]);
                $created++;
            }
        });

        return $created;
    }

    public function collectFee(HostelFee $fee, string $mode): HostelFee
    {
        return DB::transaction(function () use ($fee, $mode) {
            $fee = HostelFee::whereKey($fee->id)->lockForUpdate()->firstOrFail();
            if ($fee->status !== 'Unpaid') {
                throw new RuntimeException('This due is already settled.');
            }
            $fee->update([
                'status' => 'Paid',
                'payment_mode' => $mode,
                'paid_at' => now(),
                'collected_by' => Auth::id(),
                'receipt_no' => 'HST-' . now()->format('ymd') . '-' . str_pad((string) $fee->id, 5, '0', STR_PAD_LEFT),
            ]);
            return $fee;
        });
    }

    /** Active residents of a block, with their room and bed, ordered by room. */
    public function residents(HostelBlock $block)
    {
        return HostelAllocation::with('student.user', 'bed.room')
            ->where('status', HostelAllocation::ACTIVE)
            ->whereHas('bed.room', fn($q) => $q->where('block_id', $block->id))
            ->get()
            ->sortBy(fn($a) => [$a->bed->room->floor, $a->bed->room->room_number, $a->bed->bed_number])
            ->values();
    }

    /**
     * Save a roll call. Students marked absent who were not already recorded absent
     * trigger a push to their guardian.
     *
     * @param array<int, array{status: string, remarks?: string}> $marks keyed by student id
     * @return int guardians alerted
     */
    public function saveRollCall(HostelBlock $block, string $date, string $rollCall, array $marks): int
    {
        $residentIds = $this->residents($block)->pluck('student_id')->all();
        $newlyAbsent = [];

        DB::transaction(function () use ($block, $date, $rollCall, $marks, $residentIds, &$newlyAbsent) {
            foreach ($marks as $studentId => $mark) {
                if (!in_array((int) $studentId, $residentIds, true) || !in_array($mark['status'] ?? '', HostelAttendance::STATUSES, true)) {
                    continue;
                }
                $existing = HostelAttendance::where('student_id', $studentId)->whereDate('date', $date)->where('roll_call', $rollCall)->first();
                if ($mark['status'] === 'Absent' && $existing?->status !== 'Absent') {
                    $newlyAbsent[] = (int) $studentId;
                }
                HostelAttendance::updateOrCreate(
                    ['student_id' => $studentId, 'date' => $date, 'roll_call' => $rollCall],
                    ['school_id' => $block->school_id, 'block_id' => $block->id, 'status' => $mark['status'], 'remarks' => $mark['remarks'] ?? null, 'marked_by' => Auth::id()]
                );
            }
        });

        $alerted = 0;
        foreach (Students::with('user')->whereIn('id', $newlyAbsent)->get() as $student) {
            if (!$student->guardian_id) {
                continue;
            }
            try {
                send_notification([$student->guardian_id], 'Hostel roll call',
                    ($student->user?->full_name ?? 'Your child') . " was marked absent at the {$rollCall} roll call on {$date}. Please contact the warden.", 'Hostel');
                $alerted++;
            } catch (Throwable) {
                // best effort
            }
        }
        return $alerted;
    }

    public function requestGatePass(Students $student, array $data, bool $guardianApproved = false): HostelGatePass
    {
        if (!HostelAllocation::where('student_id', $student->id)->where('status', HostelAllocation::ACTIVE)->exists()) {
            throw new RuntimeException('Only hostel residents need a gate pass.');
        }
        $out = Carbon::parse($data['out_time']);
        $in = Carbon::parse($data['expected_in_time']);
        if ($in->lessThanOrEqualTo($out)) {
            throw new RuntimeException('The return time must be after the time out.');
        }

        $pass = HostelGatePass::create([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'pass_type' => $data['pass_type'],
            'reason' => $data['reason'] ?? null,
            'out_time' => $out,
            'expected_in_time' => $in,
            'guardian_status' => $guardianApproved ? 'Approved' : 'Pending',
            'warden_status' => 'Pending',
            'status' => 'Requested',
            'code' => $this->uniqueCode(),
            'requested_by' => Auth::id(),
        ]);

        if (!$guardianApproved && $student->guardian_id) {
            try {
                send_notification([$student->guardian_id], 'Gate pass request',
                    ($student->user?->full_name ?? 'Your child') . ' requested a ' . str_replace('_', ' ', $pass->pass_type) . ' pass. Approve or reject it in the app.', 'Hostel');
            } catch (Throwable) {
            }
        }
        return $pass;
    }

    /** $by is 'guardian' or 'warden'; the pass becomes Approved once both have approved. */
    public function decideGatePass(HostelGatePass $pass, string $by, bool $approve): HostelGatePass
    {
        return DB::transaction(function () use ($pass, $by, $approve) {
            $pass = HostelGatePass::whereKey($pass->id)->lockForUpdate()->firstOrFail();
            if ($pass->status !== 'Requested') {
                throw new RuntimeException("This pass is already {$pass->status}.");
            }
            $pass->{$by . '_status'} = $approve ? 'Approved' : 'Rejected';
            if (!$approve) {
                $pass->status = 'Rejected';
            } elseif ($pass->guardian_status === 'Approved' && $pass->warden_status === 'Approved') {
                $pass->status = 'Approved';
                $pass->approved_by = $by === 'warden' ? Auth::id() : $pass->approved_by;
            }
            if ($by === 'warden' && $approve) {
                $pass->approved_by = Auth::id();
            }
            $pass->save();
            return $pass;
        });
    }

    /** Security desk scan: an approved pass checks the student out, an out pass checks them in. */
    public function scanGatePass(string $code): HostelGatePass
    {
        return DB::transaction(function () use ($code) {
            $pass = HostelGatePass::with('student.user')->where('code', strtoupper(trim($code)))->lockForUpdate()->first();
            if (!$pass) {
                throw new RuntimeException('Unknown gate pass code.');
            }
            if ($pass->status === 'Approved') {
                if (now()->lessThan($pass->out_time->copy()->subHour())) {
                    throw new RuntimeException('This pass is valid from ' . $pass->out_time->format('d M H:i') . '.');
                }
                $pass->update(['status' => 'Out', 'actual_out_time' => now()]);
            } elseif ($pass->status === 'Out') {
                $pass->update(['status' => 'Returned', 'actual_in_time' => now()]);
            } else {
                throw new RuntimeException("This pass is {$pass->status} and cannot be used.");
            }
            return $pass;
        });
    }

    private function assertBlockAccepts(HostelBlock $block, Students $student): void
    {
        $gender = strtolower((string) $student->user?->gender);
        if ($block->type === 'Staff') {
            throw new RuntimeException('This block is for staff.');
        }
        if ($block->type === 'Boys' && $gender === 'female') {
            throw new RuntimeException('This is a boys\' block.');
        }
        if ($block->type === 'Girls' && $gender === 'male') {
            throw new RuntimeException('This is a girls\' block.');
        }
    }

    private function bedLetter(int $index): string
    {
        $letters = '';
        $index++;
        while ($index > 0) {
            $index--;
            $letters = chr(65 + $index % 26) . $letters;
            $index = intdiv($index, 26);
        }
        return $letters;
    }

    private function uniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
            $code = preg_replace('/[^A-Z0-9]/', 'X', $code);
        } while (HostelGatePass::where('code', $code)->exists());
        return $code;
    }
}
