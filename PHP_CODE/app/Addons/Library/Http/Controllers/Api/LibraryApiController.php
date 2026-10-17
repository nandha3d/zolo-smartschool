<?php

namespace App\Addons\Library\Http\Controllers\Api;

use App\Addons\Library\LibraryServiceProvider;
use App\Addons\Library\Models\LibraryBook;
use App\Addons\Library\Models\LibraryBookCopy;
use App\Addons\Library\Models\LibraryDigitalAccessLog;
use App\Addons\Library\Models\LibraryFine;
use App\Addons\Library\Models\LibraryReservation;
use App\Addons\Library\Services\LibraryService;
use App\Http\Controllers\Controller;
use App\Models\Students;
use App\Models\User;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Mobile endpoints (student, staff and parent apps). A guardian acts for one of their
 * own children through child_id; everyone else acts for themselves.
 */
class LibraryApiController extends Controller
{
    public function __construct(private LibraryService $library)
    {
    }

    public function loans(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(LibraryServiceProvider::FEATURE);
        $member = $this->library->memberFor($this->subject($request));
        $summary = $this->library->memberSummary($member);
        $summary['fines'] = $member->fines()->where('status', LibraryFine::UNPAID)->get(['id', 'reason', 'amount', 'overdue_days', 'created_at']);
        $summary['reservations'] = LibraryReservation::with('book:id,title,author')->where('member_id', $member->id)
            ->whereIn('status', LibraryReservation::ACTIVE)->get()
            ->map(fn($r) => ['id' => $r->id, 'title' => $r->book?->title, 'status' => $r->status, 'collect_by' => $r->expiry_date?->toDateString()]);
        $summary['history'] = $member->issues()->with('copy.book')->whereNotIn('status', ['Issued', 'Overdue'])
            ->latest('return_date')->limit(20)->get()->map(fn($i) => $this->library->issueRow($i));
        return response()->json(['error' => false, 'data' => $summary]);
    }

    /** Online public access catalogue: search by title, author, ISBN or category. */
    public function catalog(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(LibraryServiceProvider::FEATURE);
        $q = trim((string) $request->input('q', ''));
        $books = LibraryBook::with('category:id,name')
            ->withCount(['copies', 'copies as available_count' => fn($c) => $c->where('status', LibraryBookCopy::AVAILABLE)])
            ->when($q !== '', fn($query) => $query->where(fn($w) => $w->where('title', 'LIKE', "%{$q}%")
                ->orWhere('author', 'LIKE', "%{$q}%")->orWhere('isbn', 'LIKE', "%{$q}%")
                ->orWhereHas('category', fn($c) => $c->where('name', 'LIKE', "%{$q}%"))))
            ->orderBy('title')
            ->paginate(min(50, max(5, (int) $request->input('per_page', 20))));

        $books->getCollection()->transform(fn(LibraryBook $b) => [
            'id' => $b->id,
            'title' => $b->title,
            'author' => $b->author,
            'publisher' => $b->publisher,
            'isbn' => $b->isbn,
            'category' => $b->category?->name,
            'location' => $b->location,
            'copies' => $b->copies_count,
            'available' => $b->available_count,
            'is_digital' => $b->is_digital && $b->digital_file_path,
            'cover_image' => $b->cover_image ? url(Storage::url($b->cover_image)) : null,
        ]);

        return response()->json(['error' => false, 'data' => $books]);
    }

    public function reserve(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(LibraryServiceProvider::FEATURE);
        $request->validate(['book_id' => 'required|integer']);
        if (!$this->library->settings()->allow_online_reservation) {
            return response()->json(['error' => true, 'message' => trans('Online reservations are switched off by the library.')], 422);
        }
        try {
            $this->library->reserve($this->library->memberFor($this->subject($request)), LibraryBook::findOrFail($request->book_id));
        } catch (RuntimeException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['error' => false, 'message' => trans('Reservation placed. You will be notified when a copy is ready.')]);
    }

    /** Stream an e-book to an authorised reader and record the access. */
    public function ebook(Request $request, $id)
    {
        ResponseService::noFeatureThenSendJson(LibraryServiceProvider::FEATURE);
        return self::streamEbook($request, LibraryBook::findOrFail($id));
    }

    public static function streamEbook(Request $request, LibraryBook $book)
    {
        if (!$book->is_digital || !$book->digital_file_path || !Storage::disk('local')->exists($book->digital_file_path)) {
            abort(404);
        }
        LibraryDigitalAccessLog::create([
            'school_id' => $book->school_id,
            'book_id' => $book->id,
            'user_id' => Auth::id(),
            'accessed_at' => now(),
            'ip_address' => $request->ip(),
            'device' => substr((string) $request->userAgent(), 0, 191),
        ]);

        $extension = pathinfo($book->digital_file_path, PATHINFO_EXTENSION);
        $name = preg_replace('/[^A-Za-z0-9 _.-]/', '', $book->title) . '.' . $extension;
        return Storage::disk('local')->response($book->digital_file_path, $name, [
            'Content-Type' => $extension === 'pdf' ? 'application/pdf' : 'application/epub+zip',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    /** The user the request acts for: a guardian's own child, otherwise the caller. */
    private function subject(Request $request): User
    {
        $user = Auth::user();
        if (!$user->hasRole('Guardian')) {
            return $user;
        }
        $request->validate(['child_id' => 'required|integer']);
        $student = Students::where('id', $request->child_id)->where('guardian_id', $user->id)->first();
        if (!$student) {
            abort(response()->json(['error' => true, 'message' => trans('Child not found.')], 404));
        }
        return User::findOrFail($student->user_id);
    }
}
