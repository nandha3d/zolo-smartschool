<?php

namespace App\Addons\Hostel\Http\Controllers\Api;

use App\Addons\Hostel\HostelServiceProvider;
use App\Addons\Hostel\Models\HostelAllocation;
use App\Addons\Hostel\Models\HostelAttendance;
use App\Addons\Hostel\Models\HostelFee;
use App\Addons\Hostel\Models\HostelGatePass;
use App\Addons\Hostel\Services\HostelService;
use App\Http\Controllers\Controller;
use App\Models\Students;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Student and parent apps: the resident's room, roll call history, dues and gate
 * passes. A student requests a pass; their guardian approves or rejects it here and
 * the warden then decides on the web panel.
 */
class HostelApiController extends Controller
{
    public function __construct(private HostelService $hostel)
    {
    }

    public function overview(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(HostelServiceProvider::FEATURE);
        $student = $this->student($request);
        $allocation = HostelAllocation::with('bed.room.block', 'bed.room.roomType')->where('student_id', $student->id)
            ->where('status', HostelAllocation::ACTIVE)->first();

        $roommates = [];
        if ($allocation) {
            $roommates = HostelAllocation::with('student.user')->where('status', HostelAllocation::ACTIVE)
                ->where('id', '!=', $allocation->id)
                ->whereHas('bed', fn($q) => $q->where('room_id', $allocation->bed->room_id))
                ->get()->map(fn($a) => $a->student?->user?->full_name)->filter()->values();
        }

        return response()->json(['error' => false, 'data' => [
            'resident' => (bool) $allocation,
            'block' => $allocation?->bed?->room?->block?->name,
            'room' => $allocation?->bed?->room?->room_number,
            'floor' => $allocation?->bed?->room?->floor,
            'bed' => $allocation?->bed?->bed_number,
            'room_type' => $allocation?->bed?->room?->roomType?->name,
            'amenities' => $allocation?->bed?->room?->roomType?->amenities ?? [],
            'since' => $allocation?->allocation_date?->toDateString(),
            'roommates' => $roommates,
            'attendance' => HostelAttendance::where('student_id', $student->id)->latest('date')->limit(14)
                ->get(['date', 'roll_call', 'status'])->map(fn($a) => ['date' => $a->date->toDateString(), 'roll_call' => $a->roll_call, 'status' => $a->status]),
            'dues' => HostelFee::where('student_id', $student->id)->where('status', 'Unpaid')->orderBy('due_date')
                ->get(['id', 'period_label', 'amount', 'due_date']),
            'gate_passes' => HostelGatePass::where('student_id', $student->id)->latest('id')->limit(20)->get()->map(fn($p) => $this->passRow($p)),
        ]]);
    }

    public function requestPass(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(HostelServiceProvider::FEATURE);
        $data = $request->validate([
            'pass_type' => ['required', Rule::in(HostelGatePass::TYPES)],
            'reason' => 'required|string|max:191',
            'out_time' => 'required|date|after:now',
            'expected_in_time' => 'required|date',
        ]);
        $student = $this->student($request);
        try {
            // A guardian asking for their own child has, by definition, approved it.
            $pass = $this->hostel->requestGatePass($student, $data, Auth::user()->hasRole('Guardian'));
        } catch (RuntimeException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['error' => false, 'message' => trans('Gate pass requested.'), 'data' => $this->passRow($pass)]);
    }

    public function decidePass(Request $request, $id): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(HostelServiceProvider::FEATURE);
        if (!Auth::user()->hasRole('Guardian')) {
            return response()->json(['error' => true, 'message' => trans('Only a guardian can approve a gate pass here.')], 403);
        }
        $request->validate(['decision' => 'required|in:approve,reject']);
        $student = $this->student($request);
        $pass = HostelGatePass::where('student_id', $student->id)->findOrFail($id);
        try {
            $pass = $this->hostel->decideGatePass($pass, 'guardian', $request->decision === 'approve');
        } catch (RuntimeException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['error' => false, 'message' => trans('Decision saved.'), 'data' => $this->passRow($pass)]);
    }

    private function passRow(HostelGatePass $p): array
    {
        return [
            'id' => $p->id,
            'code' => in_array($p->status, ['Approved', 'Out'], true) ? $p->code : null,
            'pass_type' => $p->pass_type,
            'reason' => $p->reason,
            'out_time' => $p->out_time?->toDateTimeString(),
            'expected_in_time' => $p->expected_in_time?->toDateTimeString(),
            'guardian_status' => $p->guardian_status,
            'warden_status' => $p->warden_status,
            'status' => $p->status,
        ];
    }

    /** The calling student, or (for a guardian) their own child given by child_id. */
    private function student(Request $request): Students
    {
        $user = Auth::user();
        if ($user->hasRole('Guardian')) {
            $request->validate(['child_id' => 'required|integer']);
            $student = Students::with('user')->where('id', $request->child_id)->where('guardian_id', $user->id)->first();
        } else {
            $student = Students::with('user')->where('user_id', $user->id)->first();
        }
        if (!$student) {
            abort(response()->json(['error' => true, 'message' => trans('Student not found.')], 404));
        }
        return $student;
    }
}
