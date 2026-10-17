<?php

namespace App\Addons\Library\Http\Controllers;

use App\Addons\Concerns\RespondsToBootstrapTable;
use App\Addons\Library\LibraryServiceProvider;
use App\Addons\Library\Models\LibraryBook;
use App\Addons\Library\Models\LibraryBookCopy;
use App\Addons\Library\Models\LibraryFine;
use App\Addons\Library\Models\LibraryIssue;
use App\Addons\Library\Models\LibraryMember;
use App\Addons\Library\Models\LibraryReservation;
use App\Addons\Library\Services\LibraryService;
use App\Http\Controllers\Controller;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/** Dashboard, loan register, fines, reservations and policy settings. */
class LibraryLedgerController extends Controller
{
    use RespondsToBootstrapTable;

    public function __construct(private LibraryService $library)
    {
    }

    public function dashboard()
    {
        ResponseService::noFeatureThenRedirect(LibraryServiceProvider::FEATURE);
        ResponseService::noPermissionThenRedirect('library-list');

        $today = Carbon::today();
        $stats = [
            'titles' => LibraryBook::count(),
            'copies' => LibraryBookCopy::count(),
            'available' => LibraryBookCopy::where('status', LibraryBookCopy::AVAILABLE)->count(),
            'on_loan' => LibraryIssue::whereIn('status', LibraryIssue::OPEN)->count(),
            'overdue' => LibraryIssue::whereIn('status', LibraryIssue::OPEN)->where('due_date', '<', $today)->count(),
            'due_today' => LibraryIssue::whereIn('status', LibraryIssue::OPEN)->whereDate('due_date', $today)->count(),
            'members' => LibraryMember::count(),
            'unpaid_fines' => (float) LibraryFine::where('status', LibraryFine::UNPAID)->sum('amount'),
            'reservations' => LibraryReservation::whereIn('status', LibraryReservation::ACTIVE)->count(),
            'issued_today' => LibraryIssue::whereDate('issue_date', $today)->count(),
            'returned_today' => LibraryIssue::whereDate('return_date', $today)->count(),
        ];
        $popular = LibraryBook::withCount(['copies as loans_count' => fn($q) => $q->join('library_issues', 'library_issues.book_copy_id', '=', 'library_book_copies.id')])
            ->orderByDesc('loans_count')->limit(5)->get(['id', 'title', 'author']);

        return view('library::dashboard', compact('stats', 'popular'));
    }

    public function issues()
    {
        ResponseService::noFeatureThenRedirect(LibraryServiceProvider::FEATURE);
        ResponseService::noPermissionThenRedirect('library-list');
        return view('library::register', [
            'title' => __('Loans'),
            'listUrl' => route('library.loans.list'),
            'statuses' => ['open' => __('On loan'), 'overdue' => __('Overdue'), LibraryIssue::RETURNED => __('Returned'), LibraryIssue::LOST => __('Lost'), '' => __('All')],
            'columns' => [
                ['title', __('Title'), null],
                ['barcode', __('Barcode'), null],
                ['member', __('Member'), null],
                ['membership_number', __('Membership no.'), null],
                ['issue_date', __('Issued'), null],
                ['due_date', __('Due'), null],
                ['return_date', __('Returned'), null],
                ['status', __('Status'), 'libraryStatusFormatter'],
                ['renewal_count', __('Renewals'), null],
                ['id', __('action'), 'libraryLoanActions'],
            ],
        ]);
    }

    public function issuesList()
    {
        ResponseService::noFeatureThenSendJson(LibraryServiceProvider::FEATURE);
        ResponseService::noPermissionThenSendJson('library-list');

        $today = Carbon::today();
        $query = LibraryIssue::with('copy.book', 'member.user')
            ->when(request('status') === 'open', fn($q) => $q->whereIn('status', LibraryIssue::OPEN))
            ->when(request('status') === 'overdue', fn($q) => $q->whereIn('status', LibraryIssue::OPEN)->where('due_date', '<', $today))
            ->when(in_array(request('status'), [LibraryIssue::RETURNED, LibraryIssue::LOST], true), fn($q) => $q->where('status', request('status')));

        return $this->tableResponse($query, fn(LibraryIssue $i) => $this->library->issueRow($i) + [
            'member' => $i->member?->user?->full_name,
            'membership_number' => $i->member?->membership_number,
            'member_type' => $i->member?->member_type,
        ], ['copy.barcode', 'member.membership_number'], ['id', 'issue_date', 'due_date', 'return_date']);
    }

    public function fines()
    {
        ResponseService::noFeatureThenRedirect(LibraryServiceProvider::FEATURE);
        ResponseService::noPermissionThenRedirect('library-list');
        return view('library::register', [
            'title' => __('Library Fines'),
            'listUrl' => route('library.fines.list'),
            'statuses' => [LibraryFine::UNPAID => __('Unpaid'), LibraryFine::PAID => __('Paid'), LibraryFine::WAIVED => __('Waived'), '' => __('All')],
            'columns' => [
                ['member', __('Member'), null],
                ['membership_number', __('Membership no.'), null],
                ['title', __('Title'), null],
                ['reason', __('Reason'), null],
                ['overdue_days', __('Days late'), null],
                ['amount', __('Amount'), null],
                ['status', __('Status'), 'libraryStatusFormatter'],
                ['payment_mode', __('Paid by'), null],
                ['paid_at', __('Paid at'), null],
                ['waived_reason', __('Waiver reason'), null],
                ['id', __('action'), 'libraryFineActions'],
            ],
        ]);
    }

    public function finesList()
    {
        ResponseService::noFeatureThenSendJson(LibraryServiceProvider::FEATURE);
        ResponseService::noPermissionThenSendJson('library-list');

        $query = LibraryFine::with('issue.copy.book', 'member.user')
            ->when(request('status'), fn($q, $s) => $q->where('status', $s));

        return $this->tableResponse($query, fn(LibraryFine $f) => [
            'id' => $f->id,
            'member' => $f->member?->user?->full_name,
            'membership_number' => $f->member?->membership_number,
            'title' => $f->issue?->copy?->book?->title,
            'reason' => $f->reason,
            'amount' => $f->amount,
            'overdue_days' => $f->overdue_days,
            'status' => $f->status,
            'payment_mode' => $f->payment_mode,
            'paid_at' => $f->paid_at?->toDateTimeString(),
            'waived_reason' => $f->waived_reason,
            'created_at' => $f->getRawOriginal('created_at'),
        ], ['member.membership_number'], ['id', 'amount', 'created_at']);
    }

    public function settleFine(Request $request, $id)
    {
        ResponseService::noFeatureThenSendJson(LibraryServiceProvider::FEATURE);
        ResponseService::noPermissionThenSendJson('library-circulation');
        $request->validate([
            'action' => 'required|in:pay,waive',
            'payment_mode' => 'required_if:action,pay|nullable|in:Cash,Online',
            'waived_reason' => 'required_if:action,waive|nullable|string|max:191',
        ]);

        $fine = LibraryFine::findOrFail($id);
        if ($fine->status !== LibraryFine::UNPAID) {
            return response()->json(['error' => true, 'message' => trans('This fine is already settled.')], 422);
        }
        $fine->update($request->action === 'pay'
            ? ['status' => LibraryFine::PAID, 'payment_mode' => $request->payment_mode, 'paid_at' => now(), 'collected_by' => Auth::id()]
            : ['status' => LibraryFine::WAIVED, 'waived_reason' => $request->waived_reason, 'collected_by' => Auth::id()]);

        return response()->json(['error' => false, 'message' => trans('Fine updated.')]);
    }

    public function reservations()
    {
        ResponseService::noFeatureThenRedirect(LibraryServiceProvider::FEATURE);
        ResponseService::noPermissionThenRedirect('library-list');
        return view('library::register', [
            'title' => __('Reservations'),
            'listUrl' => route('library.reservations.list'),
            'statuses' => ['' => __('Active'), LibraryReservation::FULFILLED => __('Fulfilled'), LibraryReservation::EXPIRED => __('Expired'), LibraryReservation::CANCELLED => __('Cancelled')],
            'columns' => [
                ['title', __('Title'), null],
                ['member', __('Member'), null],
                ['membership_number', __('Membership no.'), null],
                ['reservation_date', __('Reserved on'), null],
                ['status', __('Status'), 'libraryStatusFormatter'],
                ['held_barcode', __('Held copy'), null],
                ['expiry_date', __('Collect by'), null],
                ['id', __('action'), 'libraryReservationActions'],
            ],
        ]);
    }

    public function reservationsList()
    {
        ResponseService::noFeatureThenSendJson(LibraryServiceProvider::FEATURE);
        ResponseService::noPermissionThenSendJson('library-list');

        $query = LibraryReservation::with('book', 'member.user', 'heldCopy')
            ->when(request('status'), fn($q, $s) => $q->where('status', $s), fn($q) => $q->whereIn('status', LibraryReservation::ACTIVE));

        return $this->tableResponse($query, fn(LibraryReservation $r) => [
            'id' => $r->id,
            'title' => $r->book?->title,
            'member' => $r->member?->user?->full_name,
            'membership_number' => $r->member?->membership_number,
            'reservation_date' => $r->reservation_date?->toDateString(),
            'expiry_date' => $r->expiry_date?->toDateString(),
            'held_barcode' => $r->heldCopy?->barcode,
            'status' => $r->status,
        ], ['book.title', 'member.membership_number'], ['id', 'reservation_date']);
    }

    public function cancelReservation($id)
    {
        ResponseService::noFeatureThenSendJson(LibraryServiceProvider::FEATURE);
        ResponseService::noPermissionThenSendJson('library-circulation');
        $reservation = LibraryReservation::findOrFail($id);
        if (!in_array($reservation->status, LibraryReservation::ACTIVE, true)) {
            return response()->json(['error' => true, 'message' => trans('This reservation is already closed.')], 422);
        }
        $this->library->cancelReservation($reservation);
        return response()->json(['error' => false, 'message' => trans('Reservation cancelled.')]);
    }

    public function settings()
    {
        ResponseService::noFeatureThenRedirect(LibraryServiceProvider::FEATURE);
        ResponseService::noPermissionThenRedirect('library-edit');
        $settings = $this->library->settings();
        return view('library::settings', compact('settings'));
    }

    public function updateSettings(Request $request)
    {
        ResponseService::noFeatureThenSendJson(LibraryServiceProvider::FEATURE);
        ResponseService::noPermissionThenSendJson('library-edit');
        $data = $request->validate([
            'student_max_books' => 'required|integer|min:0|max:50',
            'student_issue_days' => 'required|integer|min:1|max:365',
            'staff_max_books' => 'required|integer|min:0|max:50',
            'staff_issue_days' => 'required|integer|min:1|max:365',
            'fine_per_day' => 'required|numeric|min:0|max:10000',
            'grace_period_days' => 'required|integer|min:0|max:30',
            'max_renewals' => 'required|integer|min:0|max:20',
            'reservation_hold_days' => 'required|integer|min:1|max:30',
        ]);
        $data['skip_holidays'] = $request->boolean('skip_holidays');
        $data['allow_online_reservation'] = $request->boolean('allow_online_reservation');
        $this->library->settings()->update($data);
        ResponseService::successResponse('Data Updated Successfully');
    }

    public function updateMember(Request $request, $id)
    {
        ResponseService::noFeatureThenSendJson(LibraryServiceProvider::FEATURE);
        ResponseService::noPermissionThenSendJson('library-edit');
        $data = $request->validate([
            'status' => ['required', Rule::in(['Active', 'Suspended', 'Expired'])],
            'max_books_allowed' => 'nullable|integer|min:0|max:50',
            'issue_duration_days' => 'nullable|integer|min:1|max:365',
        ]);
        LibraryMember::findOrFail($id)->update($data);
        return response()->json(['error' => false, 'message' => trans('Data Updated Successfully')]);
    }
}
