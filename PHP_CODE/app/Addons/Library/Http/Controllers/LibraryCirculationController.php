<?php

namespace App\Addons\Library\Http\Controllers;

use App\Addons\Library\LibraryServiceProvider;
use App\Addons\Library\Models\LibraryBook;
use App\Addons\Library\Models\LibraryBookCopy;
use App\Addons\Library\Models\LibraryIssue;
use App\Addons\Library\Models\LibraryMember;
use App\Addons\Library\Services\LibraryService;
use App\Http\Controllers\Controller;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Throwable;

/** The librarian's issue / return terminal. Every endpoint answers JSON. */
class LibraryCirculationController extends Controller
{
    public function __construct(private LibraryService $library)
    {
    }

    public function index()
    {
        ResponseService::noFeatureThenRedirect(LibraryServiceProvider::FEATURE);
        ResponseService::noPermissionThenRedirect('library-circulation');
        return view('library::circulation');
    }

    public function member(Request $request): JsonResponse
    {
        $this->guard();
        $request->validate(['code' => 'required|string|max:64']);
        $member = $this->library->findMember($request->code);
        if (!$member) {
            return response()->json(['error' => true, 'message' => trans('No student or staff member matches this card.')], 404);
        }
        return response()->json(['error' => false, 'member' => $this->library->memberSummary($member)]);
    }

    public function copy(Request $request): JsonResponse
    {
        $this->guard();
        $request->validate(['barcode' => 'required|string|max:64']);
        $copy = LibraryBookCopy::with('book', 'openIssue.member.user')->where('barcode', trim($request->barcode))->first();
        if (!$copy) {
            return response()->json(['error' => true, 'message' => trans('No copy with this barcode.')], 404);
        }
        return response()->json(['error' => false, 'copy' => [
            'id' => $copy->id,
            'barcode' => $copy->barcode,
            'status' => $copy->status,
            'condition' => $copy->condition,
            'title' => $copy->book?->title,
            'author' => $copy->book?->author,
            'location' => $copy->book?->location,
            'borrower' => $copy->openIssue?->member?->user?->full_name,
            'due_date' => $copy->openIssue?->due_date?->toDateString(),
        ]]);
    }

    public function issue(Request $request): JsonResponse
    {
        $this->guard();
        $request->validate(['member_id' => 'required|integer', 'barcode' => 'required|string|max:64']);
        return $this->run(function () use ($request) {
            $member = LibraryMember::findOrFail($request->member_id);
            $issue = $this->library->issue($member, $request->barcode, Auth::id());
            return [
                'message' => trans('Issued. Due on :date', ['date' => $issue->due_date->toDateString()]),
                'member' => $this->library->memberSummary($member->fresh()),
            ];
        });
    }

    public function return(Request $request): JsonResponse
    {
        $this->guard();
        $request->validate(['barcode' => 'required|string|max:64', 'condition' => 'nullable|string|max:8']);
        return $this->run(function () use ($request) {
            $result = $this->library->returnCopy($request->barcode, Auth::id(), $request->condition);
            $message = trans('Returned.');
            if ($result['fine']) {
                $message .= ' ' . trans('Overdue fine :amount for :days day(s).', ['amount' => number_format((float) $result['fine']->amount, 2), 'days' => $result['fine']->overdue_days]);
            }
            if ($result['reserved_for']) {
                $message .= ' ' . trans('Hold this copy for :name (reservation).', ['name' => $result['reserved_for']]);
            }
            return [
                'message' => $message,
                'fine' => $result['fine']?->amount,
                'reserved_for' => $result['reserved_for'],
                'member' => $this->library->memberSummary($result['issue']->member),
            ];
        });
    }

    public function renew($issueId): JsonResponse
    {
        $this->guard();
        return $this->run(function () use ($issueId) {
            $issue = $this->library->renew(LibraryIssue::findOrFail($issueId));
            return [
                'message' => trans('Renewed. New due date :date', ['date' => $issue->due_date->toDateString()]),
                'member' => $this->library->memberSummary($issue->member),
            ];
        });
    }

    public function lost($issueId): JsonResponse
    {
        $this->guard();
        return $this->run(function () use ($issueId) {
            $issue = LibraryIssue::findOrFail($issueId);
            $fine = $this->library->markLost($issue, Auth::id());
            return [
                'message' => trans('Marked lost. Replacement charge :amount added to the member.', ['amount' => number_format((float) $fine->amount, 2)]),
                'member' => $this->library->memberSummary($issue->member),
            ];
        });
    }

    public function reserve(Request $request): JsonResponse
    {
        $this->guard();
        $request->validate(['member_id' => 'required|integer', 'book_id' => 'required|integer']);
        return $this->run(function () use ($request) {
            $this->library->reserve(LibraryMember::findOrFail($request->member_id), LibraryBook::findOrFail($request->book_id));
            return ['message' => trans('Reservation placed. The member will be notified when a copy is back.')];
        });
    }

    /** Title search for the terminal's reservation box. */
    public function books(Request $request): JsonResponse
    {
        $this->guard();
        $q = trim((string) $request->input('q', ''));
        $books = LibraryBook::withCount(['copies as available_count' => fn($c) => $c->where('status', LibraryBookCopy::AVAILABLE)])
            ->when($q !== '', fn($query) => $query->where(fn($w) => $w->where('title', 'LIKE', "%{$q}%")->orWhere('author', 'LIKE', "%{$q}%")->orWhere('isbn', 'LIKE', "%{$q}%")))
            ->orderBy('title')->limit(15)->get(['id', 'title', 'author']);
        return response()->json(['error' => false, 'books' => $books]);
    }

    private function guard(): void
    {
        ResponseService::noFeatureThenSendJson(LibraryServiceProvider::FEATURE);
        ResponseService::noPermissionThenSendJson('library-circulation');
    }

    private function run(callable $action): JsonResponse
    {
        try {
            return response()->json(['error' => false] + $action());
        } catch (RuntimeException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'LibraryCirculationController');
            return response()->json(['error' => true, 'message' => trans('error_occurred')], 500);
        }
    }
}
