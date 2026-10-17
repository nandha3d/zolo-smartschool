<?php

namespace App\Addons\Hostel\Http\Controllers;

use App\Addons\Concerns\RespondsToBootstrapTable;
use App\Addons\Hostel\HostelServiceProvider;
use App\Addons\Hostel\Models\HostelAllocation;
use App\Addons\Hostel\Models\HostelFee;
use App\Addons\Hostel\Models\HostelGatePass;
use App\Addons\Hostel\Models\HostelVisitor;
use App\Addons\Hostel\Services\HostelService;
use App\Http\Controllers\Controller;
use App\Models\Students;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Gate passes, the security desk scan, the visitor log and hostel fee dues. */
class HostelGateController extends Controller
{
    use RespondsToBootstrapTable;

    public function __construct(private HostelService $hostel)
    {
    }

    public function passes()
    {
        ResponseService::noFeatureThenRedirect(HostelServiceProvider::FEATURE);
        ResponseService::noPermissionThenRedirect('hostel-gate-pass');
        return view('hostel::passes');
    }

    public function passesList()
    {
        $this->guard('hostel-gate-pass');
        $query = HostelGatePass::with('student.user')
            ->when(request('status') === 'late', fn($q) => $q->where('status', 'Out')->where('expected_in_time', '<', now()))
            ->when(request('status') && request('status') !== 'late', fn($q) => $q->where('status', request('status')));

        return $this->tableResponse($query, fn(HostelGatePass $p) => [
            'id' => $p->id,
            'code' => $p->code,
            'student' => $p->student?->user?->full_name,
            'admission_no' => $p->student?->admission_no,
            'pass_type' => str_replace('_', ' ', $p->pass_type),
            'reason' => $p->reason,
            'out_time' => $p->out_time?->format('Y-m-d H:i'),
            'expected_in_time' => $p->expected_in_time?->format('Y-m-d H:i'),
            'actual_out_time' => $p->actual_out_time?->format('Y-m-d H:i'),
            'actual_in_time' => $p->actual_in_time?->format('Y-m-d H:i'),
            'guardian_status' => $p->guardian_status,
            'warden_status' => $p->warden_status,
            'status' => $p->status,
            'late' => $p->isOverdue(),
        ], ['code', 'student.admission_no'], ['id', 'out_time']);
    }

    /** A warden raising a pass on a student's behalf (e.g. a guardian who phoned in). */
    public function storePass(Request $request): JsonResponse
    {
        $this->guard('hostel-gate-pass');
        $data = $request->validate([
            'student_id' => 'required|integer',
            'pass_type' => ['required', Rule::in(HostelGatePass::TYPES)],
            'reason' => 'nullable|string|max:191',
            'out_time' => 'required|date',
            'expected_in_time' => 'required|date',
            'guardian_confirmed' => 'nullable|boolean',
        ]);
        return $this->run(fn() => $this->hostel->requestGatePass(Students::with('user')->findOrFail($data['student_id']), $data, $request->boolean('guardian_confirmed')), 'Gate pass created.');
    }

    public function decide(Request $request, $id): JsonResponse
    {
        $this->guard('hostel-gate-pass');
        $request->validate(['decision' => 'required|in:approve,reject']);
        return $this->run(fn() => $this->hostel->decideGatePass(HostelGatePass::findOrFail($id), 'warden', $request->decision === 'approve'), 'Decision saved.');
    }

    public function cancel($id): JsonResponse
    {
        $this->guard('hostel-gate-pass');
        $pass = HostelGatePass::findOrFail($id);
        if (!in_array($pass->status, ['Requested', 'Approved'], true)) {
            return response()->json(['error' => true, 'message' => trans('Only passes not yet used can be cancelled.')], 422);
        }
        $pass->update(['status' => 'Cancelled']);
        return response()->json(['error' => false, 'message' => trans('Gate pass cancelled.')]);
    }

    public function scan(Request $request): JsonResponse
    {
        $this->guard('hostel-gate-pass');
        $request->validate(['code' => 'required|string|max:12']);
        try {
            $pass = $this->hostel->scanGatePass($request->code);
        } catch (RuntimeException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 422);
        }
        $name = $pass->student?->user?->full_name;
        $message = $pass->status === 'Out'
            ? trans(':name checked OUT. Due back :time.', ['name' => $name, 'time' => $pass->expected_in_time->format('d M H:i')])
            : trans(':name checked IN.', ['name' => $name]) . ($pass->actual_in_time->greaterThan($pass->expected_in_time) ? ' ' . trans('Returned late.') : '');
        return response()->json(['error' => false, 'message' => $message, 'status' => $pass->status, 'image' => $pass->student?->user?->image]);
    }

    public function visitors()
    {
        ResponseService::noFeatureThenRedirect(HostelServiceProvider::FEATURE);
        ResponseService::noPermissionThenRedirect('hostel-gate-pass');
        return view('hostel::visitors');
    }

    public function visitorsList()
    {
        $this->guard('hostel-gate-pass');
        $query = HostelVisitor::with('student.user')
            ->when(request('status') === 'inside', fn($q) => $q->whereNull('out_time'));
        return $this->tableResponse($query, fn(HostelVisitor $v) => [
            'id' => $v->id,
            'visitor_name' => $v->visitor_name,
            'relation' => $v->relation,
            'contact' => $v->contact,
            'id_proof' => $v->id_proof,
            'purpose' => $v->purpose,
            'student' => $v->student?->user?->full_name,
            'in_time' => $v->in_time?->format('Y-m-d H:i'),
            'out_time' => $v->out_time?->format('Y-m-d H:i'),
        ], ['visitor_name', 'contact', 'student.admission_no'], ['id', 'in_time']);
    }

    public function storeVisitor(Request $request): JsonResponse
    {
        $this->guard('hostel-gate-pass');
        $data = $request->validate([
            'student_id' => 'required|integer',
            'visitor_name' => 'required|string|max:191',
            'relation' => 'nullable|string|max:64',
            'contact' => 'nullable|string|max:32',
            'id_proof' => 'nullable|string|max:64',
            'purpose' => 'nullable|string|max:191',
        ]);
        if (!HostelAllocation::where('student_id', $data['student_id'])->where('status', HostelAllocation::ACTIVE)->exists()) {
            return response()->json(['error' => true, 'message' => trans('This student is not a hostel resident.')], 422);
        }
        HostelVisitor::create($data + ['in_time' => now(), 'logged_by' => Auth::id()]);
        return response()->json(['error' => false, 'message' => trans('Visitor checked in.')]);
    }

    public function checkoutVisitor($id): JsonResponse
    {
        $this->guard('hostel-gate-pass');
        $visitor = HostelVisitor::whereNull('out_time')->findOrFail($id);
        $visitor->update(['out_time' => now()]);
        return response()->json(['error' => false, 'message' => trans('Visitor checked out.')]);
    }

    public function fees()
    {
        ResponseService::noFeatureThenRedirect(HostelServiceProvider::FEATURE);
        ResponseService::noPermissionThenRedirect('hostel-fees');
        return view('hostel::fees');
    }

    public function feesList()
    {
        $this->guard('hostel-fees');
        $query = HostelFee::with('student.user')->when(request('status'), fn($q, $s) => $q->where('status', $s));
        return $this->tableResponse($query, fn(HostelFee $f) => [
            'id' => $f->id,
            'student' => $f->student?->user?->full_name,
            'admission_no' => $f->student?->admission_no,
            'period_label' => $f->period_label,
            'amount' => $f->amount,
            'due_date' => $f->due_date?->toDateString(),
            'status' => $f->status,
            'overdue' => $f->status === 'Unpaid' && $f->due_date?->isPast(),
            'payment_mode' => $f->payment_mode,
            'receipt_no' => $f->receipt_no,
            'paid_at' => $f->paid_at?->format('Y-m-d H:i'),
        ], ['student.admission_no', 'receipt_no'], ['id', 'due_date', 'period_start']);
    }

    public function generateFees(Request $request): JsonResponse
    {
        $this->guard('hostel-fees');
        $request->validate(['month' => 'required|date_format:Y-m', 'due_in_days' => 'nullable|integer|min:0|max:60']);
        $count = $this->hostel->generateFees($request->month . '-01', (int) $request->input('due_in_days', 10));
        return response()->json(['error' => false, 'message' => trans(':count due(s) raised.', ['count' => $count])]);
    }

    public function settleFee(Request $request, $id): JsonResponse
    {
        $this->guard('hostel-fees');
        $request->validate(['action' => 'required|in:pay,waive', 'payment_mode' => 'nullable|in:Cash,Cheque,Online,Bank']);
        $fee = HostelFee::findOrFail($id);
        if ($request->action === 'waive') {
            if ($fee->status !== 'Unpaid') {
                return response()->json(['error' => true, 'message' => trans('This due is already settled.')], 422);
            }
            $fee->update(['status' => 'Waived', 'collected_by' => Auth::id()]);
            return response()->json(['error' => false, 'message' => trans('Due waived.')]);
        }
        return $this->run(fn() => $this->hostel->collectFee($fee, $request->input('payment_mode', 'Cash')), 'Payment recorded.');
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
