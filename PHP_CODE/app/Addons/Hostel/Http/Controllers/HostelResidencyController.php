<?php

namespace App\Addons\Hostel\Http\Controllers;

use App\Addons\Concerns\RespondsToBootstrapTable;
use App\Addons\Hostel\HostelServiceProvider;
use App\Addons\Hostel\Models\HostelAllocation;
use App\Addons\Hostel\Models\HostelAttendance;
use App\Addons\Hostel\Models\HostelBed;
use App\Addons\Hostel\Models\HostelBlock;
use App\Addons\Hostel\Models\HostelFee;
use App\Addons\Hostel\Models\HostelGatePass;
use App\Addons\Hostel\Services\HostelService;
use App\Http\Controllers\Controller;
use App\Models\Students;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Dashboard, the bed board / allocations, and roll call. */
class HostelResidencyController extends Controller
{
    use RespondsToBootstrapTable;

    public function __construct(private HostelService $hostel)
    {
    }

    public function dashboard()
    {
        ResponseService::noFeatureThenRedirect(HostelServiceProvider::FEATURE);
        ResponseService::noPermissionThenRedirect('hostel-list');

        $blocks = HostelBlock::withCount('rooms')->orderBy('name')->get()->map(function (HostelBlock $block) {
            $beds = HostelBed::whereHas('room', fn($q) => $q->where('block_id', $block->id));
            $total = (clone $beds)->count();
            $occupied = (clone $beds)->whereNotNull('current_allocation_id')->count();
            return ['block' => $block, 'beds' => $total, 'occupied' => $occupied];
        });

        $today = Carbon::today()->toDateString();
        $stats = [
            'residents' => HostelAllocation::where('status', HostelAllocation::ACTIVE)->count(),
            'out_now' => HostelGatePass::where('status', 'Out')->count(),
            'overdue_return' => HostelGatePass::where('status', 'Out')->where('expected_in_time', '<', now())->count(),
            'pending_passes' => HostelGatePass::where('status', 'Requested')->count(),
            'absent_tonight' => HostelAttendance::whereDate('date', $today)->where('roll_call', 'Night')->where('status', 'Absent')->count(),
            'unpaid' => (float) HostelFee::where('status', 'Unpaid')->sum('amount'),
        ];
        return view('hostel::dashboard', compact('blocks', 'stats'));
    }

    public function allocations()
    {
        ResponseService::noFeatureThenRedirect(HostelServiceProvider::FEATURE);
        ResponseService::noPermissionThenRedirect('hostel-list');
        $blocks = HostelBlock::orderBy('name')->get();
        return view('hostel::allocations', compact('blocks'));
    }

    public function allocationsList()
    {
        $this->guard('hostel-list');
        $query = HostelAllocation::with('student.user', 'student.class_section.class', 'student.class_section.section', 'bed.room.block')
            ->when(request('status', HostelAllocation::ACTIVE), fn($q, $s) => $q->where('status', $s))
            ->when(request('block_id'), fn($q, $id) => $q->whereHas('bed.room', fn($r) => $r->where('block_id', $id)));

        return $this->tableResponse($query, fn(HostelAllocation $a) => [
            'id' => $a->id,
            'student' => $a->student?->user?->full_name,
            'admission_no' => $a->student?->admission_no,
            'class' => trim(($a->student?->class_section?->class?->name ?? '') . ' ' . ($a->student?->class_section?->section?->name ?? '')),
            'block' => $a->bed?->room?->block?->name,
            'room' => $a->bed?->room?->room_number,
            'bed' => $a->bed?->bed_number,
            'allocation_date' => $a->allocation_date?->toDateString(),
            'vacate_date' => $a->vacate_date?->toDateString(),
            'status' => $a->status,
            'fee_amount' => $a->fee_amount,
        ], ['student.admission_no'], ['id', 'allocation_date']);
    }

    /** Students matching a name or admission number, with any current bed. */
    public function students(Request $request): JsonResponse
    {
        $this->guard('hostel-list');
        $q = trim((string) $request->input('q', ''));
        $students = Students::with('user:id,first_name,last_name,gender', 'class_section.class', 'class_section.section')
            ->when($q !== '', fn($query) => $query->where(fn($w) => $w->where('admission_no', 'LIKE', "%{$q}%")
                ->orWhereHas('user', fn($u) => $u->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$q}%"]))))
            ->limit(15)->get();
        $allocated = HostelAllocation::with('bed.room.block')->where('status', HostelAllocation::ACTIVE)
            ->whereIn('student_id', $students->pluck('id'))->get()->keyBy('student_id');

        return response()->json(['error' => false, 'students' => $students->map(fn($s) => [
            'id' => $s->id,
            'name' => $s->user?->full_name,
            'admission_no' => $s->admission_no,
            'gender' => $s->user?->gender,
            'class' => trim(($s->class_section?->class?->name ?? '') . ' ' . ($s->class_section?->section?->name ?? '')),
            'allocation_id' => $allocated[$s->id]?->id ?? null,
            'bed' => isset($allocated[$s->id]) ? $allocated[$s->id]->bed->room->block->name . ' / ' . $allocated[$s->id]->bed->bed_number : null,
        ])]);
    }

    public function allocate(Request $request): JsonResponse
    {
        $this->guard('hostel-create');
        $data = $request->validate([
            'student_id' => 'required|integer',
            'bed_id' => 'required|integer',
            'allocation_date' => 'nullable|date',
            'fee_amount' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:191',
        ]);
        return $this->run(fn() => $this->hostel->allocate($data['student_id'], $data['bed_id'], $data['allocation_date'] ?? null,
            isset($data['fee_amount']) ? (float) $data['fee_amount'] : null, $data['remarks'] ?? null), 'Bed allocated.');
    }

    public function vacate(Request $request, $id): JsonResponse
    {
        $this->guard('hostel-edit');
        $request->validate(['vacate_date' => 'nullable|date']);
        return $this->run(fn() => $this->hostel->vacate(HostelAllocation::findOrFail($id), $request->vacate_date), 'Bed vacated.');
    }

    public function transfer(Request $request, $id): JsonResponse
    {
        $this->guard('hostel-edit');
        $request->validate(['bed_id' => 'required|integer']);
        return $this->run(fn() => $this->hostel->transfer(HostelAllocation::findOrFail($id), (int) $request->bed_id), 'Student moved to the new bed.');
    }

    public function attendance()
    {
        ResponseService::noFeatureThenRedirect(HostelServiceProvider::FEATURE);
        ResponseService::noPermissionThenRedirect('hostel-attendance');
        $blocks = HostelBlock::orderBy('name')->get();
        return view('hostel::attendance', compact('blocks'));
    }

    public function rollCall(Request $request): JsonResponse
    {
        $this->guard('hostel-attendance');
        $request->validate(['block_id' => 'required|integer', 'date' => 'required|date', 'roll_call' => ['required', Rule::in(['Morning', 'Night'])]]);
        $block = HostelBlock::findOrFail($request->block_id);
        $marks = HostelAttendance::whereDate('date', $request->date)->where('roll_call', $request->roll_call)
            ->where('block_id', $block->id)->get()->keyBy('student_id');
        $out = HostelGatePass::whereIn('status', ['Out', 'Approved'])
            ->where('out_time', '<=', Carbon::parse($request->date)->endOfDay())
            ->where('expected_in_time', '>=', Carbon::parse($request->date)->startOfDay())
            ->pluck('student_id')->flip();

        $rows = $this->hostel->residents($block)->map(fn(HostelAllocation $a) => [
            'student_id' => $a->student_id,
            'name' => $a->student?->user?->full_name,
            'admission_no' => $a->student?->admission_no,
            'room' => $a->bed->room->room_number,
            'bed' => $a->bed->bed_number,
            'status' => $marks[$a->student_id]->status ?? (isset($out[$a->student_id]) ? 'On_Leave' : null),
            'remarks' => $marks[$a->student_id]->remarks ?? null,
            'on_pass' => isset($out[$a->student_id]),
        ]);
        return response()->json(['error' => false, 'residents' => $rows, 'saved' => $marks->isNotEmpty()]);
    }

    public function saveRollCall(Request $request): JsonResponse
    {
        $this->guard('hostel-attendance');
        $request->validate([
            'block_id' => 'required|integer',
            'date' => 'required|date|before_or_equal:today',
            'roll_call' => ['required', Rule::in(['Morning', 'Night'])],
            'marks' => 'required|array',
            'marks.*.status' => ['required', Rule::in(HostelAttendance::STATUSES)],
            'marks.*.remarks' => 'nullable|string|max:191',
        ]);
        $alerted = $this->hostel->saveRollCall(HostelBlock::findOrFail($request->block_id), Carbon::parse($request->date)->toDateString(), $request->roll_call, $request->marks);
        return response()->json(['error' => false, 'message' => trans('Roll call saved.') . ($alerted ? ' ' . trans(':count guardian(s) alerted.', ['count' => $alerted]) : '')]);
    }

    private function guard(string $permission): void
    {
        ResponseService::noFeatureThenSendJson(HostelServiceProvider::FEATURE);
        ResponseService::noPermissionThenSendJson($permission);
    }

    private function run(callable $action, string $message): JsonResponse
    {
        try {
            $action();
        } catch (RuntimeException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['error' => false, 'message' => trans($message)]);
    }
}
