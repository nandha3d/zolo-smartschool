<?php

namespace App\Addons\Library\Http\Controllers;

use App\Addons\Concerns\RespondsToBootstrapTable;
use App\Addons\Library\Http\Controllers\Api\LibraryApiController;
use App\Addons\Library\LibraryServiceProvider;
use App\Addons\Library\Models\LibraryBook;
use App\Addons\Library\Models\LibraryBookCopy;
use App\Addons\Library\Models\LibraryCategory;
use App\Http\Controllers\Controller;
use App\Services\BootstrapTableService;
use App\Services\ResponseService;
use App\Services\UploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class LibraryCatalogController extends Controller
{
    use RespondsToBootstrapTable;

    public function index()
    {
        ResponseService::noFeatureThenRedirect(LibraryServiceProvider::FEATURE);
        ResponseService::noPermissionThenRedirect('library-list');

        $categories = LibraryCategory::orderBy('name')->get();
        return view('library::catalog', compact('categories'));
    }

    public function list()
    {
        ResponseService::noFeatureThenSendJson(LibraryServiceProvider::FEATURE);
        ResponseService::noPermissionThenSendJson('library-list');

        $query = LibraryBook::with('category')
            ->withCount([
                'copies',
                'copies as available_count' => fn($q) => $q->where('status', LibraryBookCopy::AVAILABLE),
            ])
            ->when(request('category_id'), fn($q, $id) => $q->where('category_id', $id));

        $canEdit = Auth::user()->can('library-edit');
        $canDelete = Auth::user()->can('library-delete');

        return $this->tableResponse($query, function (LibraryBook $book) use ($canEdit, $canDelete) {
            $operate = BootstrapTableService::button('fa fa-barcode', '#', ['btn-gradient-info', 'library-copies'], ['title' => trans('Copies'), 'data-id' => $book->id, 'data-title' => e($book->title)]);
            if ($canEdit) {
                $operate .= BootstrapTableService::editButton(route('library.books.update', $book->id));
            }
            if ($canDelete) {
                $operate .= BootstrapTableService::deleteButton(route('library.books.destroy', $book->id));
            }
            return [
                'id' => $book->id,
                'title' => $book->title,
                'subtitle' => $book->subtitle,
                'author' => $book->author,
                'publisher' => $book->publisher,
                'isbn' => $book->isbn,
                'edition' => $book->edition,
                'publication_year' => $book->publication_year,
                'language' => $book->language,
                'price' => $book->price,
                'dewey_decimal_code' => $book->dewey_decimal_code,
                'rack_location' => $book->rack_location,
                'shelf_number' => $book->shelf_number,
                'category_id' => $book->category_id,
                'category' => $book->category?->name,
                'description' => $book->description,
                'is_digital' => $book->is_digital,
                'has_file' => (bool) $book->digital_file_path,
                'location' => $book->location,
                'copies' => $book->copies_count,
                'available' => $book->available_count,
                'operate' => $operate,
            ];
        }, ['title', 'author', 'isbn', 'publisher'], ['id', 'title', 'author', 'publication_year']);
    }

    public function store(Request $request)
    {
        ResponseService::noFeatureThenSendJson(LibraryServiceProvider::FEATURE);
        ResponseService::noPermissionThenSendJson('library-create');
        $data = $this->validated($request);

        try {
            DB::beginTransaction();
            $book = LibraryBook::create($data);
            $this->storeDigitalFile($request, $book);
            $copies = (int) $request->input('copies', 1);
            if ($copies > 0) {
                $this->createCopies($book, $copies, $request->input('acquisition_type', 'Purchase'));
            }
            DB::commit();
            ResponseService::successResponse('Data Stored Successfully');
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, 'LibraryCatalogController -> store');
            ResponseService::errorResponse();
        }
    }

    public function update(Request $request, $id)
    {
        ResponseService::noFeatureThenSendJson(LibraryServiceProvider::FEATURE);
        ResponseService::noPermissionThenSendJson('library-edit');
        $data = $this->validated($request);

        try {
            $book = LibraryBook::findOrFail($id);
            $book->update($data);
            $this->storeDigitalFile($request, $book);
            ResponseService::successResponse('Data Updated Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'LibraryCatalogController -> update');
            ResponseService::errorResponse();
        }
    }

    public function destroy($id)
    {
        ResponseService::noFeatureThenSendJson(LibraryServiceProvider::FEATURE);
        ResponseService::noPermissionThenSendJson('library-delete');
        $book = LibraryBook::findOrFail($id);
        if ($book->copies()->whereIn('status', [LibraryBookCopy::ISSUED, LibraryBookCopy::RESERVED])->exists()) {
            ResponseService::errorResponse('Copies of this book are on loan or held. Return them first.');
        }
        $book->delete();
        ResponseService::successResponse('Data Deleted Successfully');
    }

    public function ebook(Request $request, $id)
    {
        ResponseService::noFeatureThenRedirect(LibraryServiceProvider::FEATURE);
        ResponseService::noPermissionThenRedirect('library-list');
        return LibraryApiController::streamEbook($request, LibraryBook::findOrFail($id));
    }

    public function copies($id)
    {
        ResponseService::noFeatureThenSendJson(LibraryServiceProvider::FEATURE);
        ResponseService::noPermissionThenSendJson('library-list');
        $book = LibraryBook::findOrFail($id);
        $copies = $book->copies()->with('openIssue.member.user')->orderBy('copy_number')->get()->map(fn(LibraryBookCopy $c) => [
            'id' => $c->id,
            'barcode' => $c->barcode,
            'accession_number' => $c->accession_number,
            'copy_number' => $c->copy_number,
            'status' => $c->status,
            'condition' => $c->condition,
            'acquisition_type' => $c->acquisition_type,
            'acquisition_date' => $c->acquisition_date?->toDateString(),
            'borrower' => $c->openIssue?->member?->user?->full_name,
            'due_date' => $c->openIssue?->due_date?->toDateString(),
        ]);
        return response()->json(['error' => false, 'book' => ['id' => $book->id, 'title' => $book->title], 'copies' => $copies]);
    }

    public function addCopies(Request $request, $id)
    {
        ResponseService::noFeatureThenSendJson(LibraryServiceProvider::FEATURE);
        ResponseService::noPermissionThenSendJson('library-create');
        $request->validate([
            'count' => 'required|integer|min:1|max:200',
            'acquisition_type' => ['nullable', Rule::in(LibraryBookCopy::ACQUISITION_TYPES)],
        ]);
        $book = LibraryBook::findOrFail($id);
        DB::transaction(fn() => $this->createCopies($book, (int) $request->count, $request->input('acquisition_type', 'Purchase')));
        return response()->json(['error' => false, 'message' => trans('Copies added')]);
    }

    public function updateCopy(Request $request, $copyId)
    {
        ResponseService::noFeatureThenSendJson(LibraryServiceProvider::FEATURE);
        ResponseService::noPermissionThenSendJson('library-edit');
        $request->validate([
            'status' => ['required', Rule::in([LibraryBookCopy::AVAILABLE, LibraryBookCopy::LOST, LibraryBookCopy::DAMAGED, LibraryBookCopy::UNDER_REPAIR])],
            'condition' => ['required', Rule::in(LibraryBookCopy::CONDITIONS)],
        ]);
        $copy = LibraryBookCopy::findOrFail($copyId);
        if (in_array($copy->status, [LibraryBookCopy::ISSUED, LibraryBookCopy::RESERVED], true) && $request->status !== $copy->status) {
            return response()->json(['error' => true, 'message' => 'This copy is on loan or held; use the circulation desk.'], 422);
        }
        $copy->update($request->only('status', 'condition'));
        return response()->json(['error' => false, 'message' => trans('Data Updated Successfully')]);
    }

    public function destroyCopy($copyId)
    {
        ResponseService::noFeatureThenSendJson(LibraryServiceProvider::FEATURE);
        ResponseService::noPermissionThenSendJson('library-delete');
        $copy = LibraryBookCopy::findOrFail($copyId);
        if ($copy->issues()->exists()) {
            return response()->json(['error' => true, 'message' => 'This copy has loan history; mark it Lost or Damaged instead.'], 422);
        }
        $copy->delete();
        return response()->json(['error' => false, 'message' => trans('Data Deleted Successfully')]);
    }

    public function storeCategory(Request $request)
    {
        ResponseService::noFeatureThenSendJson(LibraryServiceProvider::FEATURE);
        ResponseService::noPermissionThenSendJson('library-create');
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'code' => 'nullable|string|max:32',
            'parent_id' => 'nullable|integer',
        ]);
        $category = LibraryCategory::create($data + ['is_active' => true]);
        return response()->json(['error' => false, 'message' => trans('Data Stored Successfully'), 'category' => $category]);
    }

    public function destroyCategory($id)
    {
        ResponseService::noFeatureThenSendJson(LibraryServiceProvider::FEATURE);
        ResponseService::noPermissionThenSendJson('library-delete');
        $category = LibraryCategory::findOrFail($id);
        $category->books()->update(['category_id' => null]);
        LibraryCategory::where('parent_id', $category->id)->update(['parent_id' => null]);
        $category->delete();
        return response()->json(['error' => false, 'message' => trans('Data Deleted Successfully')]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:191',
            'subtitle' => 'nullable|string|max:191',
            'author' => 'nullable|string|max:191',
            'publisher' => 'nullable|string|max:191',
            'isbn' => ['nullable', 'string', 'max:20', 'regex:/^[0-9Xx-]{10,17}$/'],
            'edition' => 'nullable|string|max:64',
            'publication_year' => 'nullable|integer|min:1000|max:' . (date('Y') + 1),
            'language' => 'nullable|string|max:64',
            'price' => 'nullable|numeric|min:0|max:9999999',
            'category_id' => 'nullable|integer',
            'dewey_decimal_code' => 'nullable|string|max:32',
            'rack_location' => 'nullable|string|max:64',
            'shelf_number' => 'nullable|string|max:64',
            'description' => 'nullable|string|max:5000',
            'is_digital' => 'nullable|boolean',
            'digital_file' => 'nullable|file|mimes:pdf,epub|max:51200',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'copies' => 'nullable|integer|min:0|max:200',
            'acquisition_type' => ['nullable', Rule::in(LibraryBookCopy::ACQUISITION_TYPES)],
        ]);

        $book = collect($data)->only([
            'title', 'subtitle', 'author', 'publisher', 'isbn', 'edition', 'publication_year', 'language',
            'price', 'category_id', 'dewey_decimal_code', 'rack_location', 'shelf_number', 'description',
        ])->all();
        $book['price'] = $book['price'] ?? 0;
        $book['is_digital'] = $request->boolean('is_digital');
        if (!empty($book['category_id']) && !LibraryCategory::whereKey($book['category_id'])->exists()) {
            $book['category_id'] = null;
        }
        if ($request->hasFile('cover_image')) {
            $book['cover_image'] = UploadService::upload($request->file('cover_image'), 'library');
        }
        return $book;
    }

    /** E-books go to the private disk and are only served through the authorised reader route. */
    private function storeDigitalFile(Request $request, LibraryBook $book): void
    {
        if (!$request->hasFile('digital_file')) {
            return;
        }
        $file = $request->file('digital_file');
        $path = $file->storeAs(
            'private/library/' . ($book->school_id ?? 0),
            $book->id . '-' . bin2hex(random_bytes(8)) . '.' . strtolower($file->extension()),
            'local'
        );
        if ($book->digital_file_path) {
            Storage::disk('local')->delete($book->digital_file_path);
        }
        $book->forceFill(['digital_file_path' => $path, 'is_digital' => true])->save();
    }

    private function createCopies(LibraryBook $book, int $count, string $acquisitionType): void
    {
        $nextCopy = (int) $book->copies()->max('copy_number') + 1;
        $nextAccession = (int) LibraryBookCopy::where('school_id', $book->school_id)->lockForUpdate()->count() + 1;
        for ($i = 0; $i < $count; $i++) {
            do {
                $accession = 'ACC-' . str_pad((string) $nextAccession++, 6, '0', STR_PAD_LEFT);
            } while (LibraryBookCopy::where('accession_number', $accession)->exists());
            LibraryBookCopy::create([
                'school_id' => $book->school_id,
                'book_id' => $book->id,
                'copy_number' => $nextCopy + $i,
                'accession_number' => $accession,
                // Short, scanner-friendly code: book id + copy number + check digit.
                'barcode' => $this->barcodeFor($book->id, $nextCopy + $i),
                'status' => LibraryBookCopy::AVAILABLE,
                'condition' => 'New',
                'acquisition_date' => now()->toDateString(),
                'acquisition_type' => $acquisitionType,
            ]);
        }
    }

    private function barcodeFor(int $bookId, int $copyNumber): string
    {
        $digits = str_pad((string) $bookId, 6, '0', STR_PAD_LEFT) . str_pad((string) $copyNumber, 3, '0', STR_PAD_LEFT);
        $sum = 0;
        foreach (str_split($digits) as $i => $d) {
            $sum += (int) $d * ($i % 2 ? 3 : 1);
        }
        return 'LB' . $digits . ((10 - $sum % 10) % 10);
    }
}
