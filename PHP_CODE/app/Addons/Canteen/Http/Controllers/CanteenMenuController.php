<?php

namespace App\Addons\Canteen\Http\Controllers;

use App\Addons\Canteen\CanteenServiceProvider;
use App\Addons\Canteen\Models\CanteenCategory;
use App\Addons\Canteen\Models\CanteenItem;
use App\Addons\Canteen\Models\CanteenOrder;
use App\Addons\Canteen\Models\CanteenOrderItem;
use App\Addons\Canteen\Models\CanteenInventoryItem;
use App\Addons\Concerns\RespondsToBootstrapTable;
use App\Http\Controllers\Controller;
use App\Services\BootstrapTableService;
use App\Services\ResponseService;
use App\Services\UploadService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Dashboard plus the menu: categories and items with dietary / allergen tags. */
class CanteenMenuController extends Controller
{
    use RespondsToBootstrapTable;

    public function dashboard()
    {
        ResponseService::noFeatureThenRedirect(CanteenServiceProvider::FEATURE);
        ResponseService::noPermissionThenRedirect('canteen-list');

        $today = Carbon::today();
        $orders = CanteenOrder::whereDate('service_date', $today)->where('status', '!=', 'Cancelled');
        $stats = [
            'orders' => (clone $orders)->count(),
            'sales' => (float) (clone $orders)->sum('total_amount'),
            'wallet_sales' => (float) (clone $orders)->where('payment_mode', 'Wallet')->sum('total_amount'),
            'open_orders' => (clone $orders)->whereIn('status', ['Pending', 'Preparing', 'Ready'])->count(),
            'low_stock' => CanteenInventoryItem::whereColumn('current_stock', '<=', 'reorder_level')->count(),
        ];
        $top = CanteenOrderItem::select('name', DB::raw('SUM(quantity) as qty'), DB::raw('SUM(subtotal) as revenue'))
            ->whereIn('order_id', (clone $orders)->pluck('id'))
            ->groupBy('name')->orderByDesc('qty')->limit(8)->get();
        $lowStock = CanteenInventoryItem::whereColumn('current_stock', '<=', 'reorder_level')->orderBy('name')->limit(10)->get();
        return view('canteen::dashboard', compact('stats', 'top', 'lowStock'));
    }

    public function index()
    {
        ResponseService::noFeatureThenRedirect(CanteenServiceProvider::FEATURE);
        ResponseService::noPermissionThenRedirect('canteen-list');
        $categories = CanteenCategory::orderBy('sort_order')->orderBy('name')->get();
        return view('canteen::menu', compact('categories'));
    }

    public function list()
    {
        $this->guard('canteen-list');
        $query = CanteenItem::with('category')->when(request('category_id'), fn($q, $id) => $q->where('category_id', $id));
        $canEdit = Auth::user()->can('canteen-edit');
        $canDelete = Auth::user()->can('canteen-delete');
        return $this->tableResponse($query, function (CanteenItem $i) use ($canEdit, $canDelete) {
            $operate = ($canEdit ? BootstrapTableService::editButton(route('canteen.items.update', $i->id)) : '')
                . ($canDelete ? BootstrapTableService::deleteButton(route('canteen.items.destroy', $i->id)) : '');
            return [
                'id' => $i->id,
                'name' => $i->name,
                'code' => $i->code,
                'category_id' => $i->category_id,
                'category' => $i->category?->name,
                'price' => $i->price,
                'cost_price' => $i->cost_price,
                'dietary_type' => $i->dietary_type,
                'allergens' => $i->allergens ?? [],
                'calories' => $i->calories,
                'is_available' => $i->is_available,
                'track_stock' => $i->track_stock,
                'stock_qty' => $i->stock_qty,
                'operate' => $operate,
            ];
        }, ['name', 'code'], ['id', 'name', 'price']);
    }

    public function store(Request $request)
    {
        $this->guard('canteen-create');
        CanteenItem::create($this->itemData($request));
        ResponseService::successResponse('Data Stored Successfully');
    }

    public function update(Request $request, $id)
    {
        $this->guard('canteen-edit');
        CanteenItem::findOrFail($id)->update($this->itemData($request));
        ResponseService::successResponse('Data Updated Successfully');
    }

    public function destroy($id)
    {
        $this->guard('canteen-delete');
        CanteenItem::findOrFail($id)->delete();
        ResponseService::successResponse('Data Deleted Successfully');
    }

    public function toggle($id): JsonResponse
    {
        $this->guard('canteen-edit');
        $item = CanteenItem::findOrFail($id);
        $item->update(['is_available' => !$item->is_available]);
        return response()->json(['error' => false, 'message' => $item->is_available ? trans('Back on the menu.') : trans('Marked sold out.')]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $this->guard('canteen-create');
        $data = $request->validate(['name' => 'required|string|max:191', 'sort_order' => 'nullable|integer|min:0|max:999']);
        CanteenCategory::create($data + ['is_active' => true, 'sort_order' => $data['sort_order'] ?? 0]);
        return response()->json(['error' => false, 'message' => trans('Data Stored Successfully')]);
    }

    public function destroyCategory($id): JsonResponse
    {
        $this->guard('canteen-delete');
        CanteenCategory::findOrFail($id)->delete();
        return response()->json(['error' => false, 'message' => trans('Data Deleted Successfully')]);
    }

    private function itemData(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'code' => 'nullable|string|max:32',
            'category_id' => 'nullable|integer',
            'price' => 'required|numeric|min:0|max:999999',
            'cost_price' => 'nullable|numeric|min:0|max:999999',
            'dietary_type' => ['required', Rule::in(CanteenItem::DIETARY)],
            'allergens' => 'nullable|array',
            'allergens.*' => ['string', Rule::in(CanteenItem::ALLERGENS)],
            'calories' => 'nullable|integer|min:0|max:5000',
            'stock_qty' => 'nullable|integer|min:0|max:100000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
        ]);
        $item = collect($data)->except('image')->all();
        $item['allergens'] = array_values($data['allergens'] ?? []);
        $item['is_available'] = $request->boolean('is_available', true);
        $item['track_stock'] = $request->boolean('track_stock');
        $item['stock_qty'] = (int) ($data['stock_qty'] ?? 0);
        if (!empty($item['category_id']) && !CanteenCategory::whereKey($item['category_id'])->exists()) {
            $item['category_id'] = null;
        }
        if ($request->hasFile('image')) {
            $item['image'] = UploadService::upload($request->file('image'), 'canteen');
        }
        return $item;
    }

    private function guard(string $permission): void
    {
        ResponseService::noFeatureThenSendJson(CanteenServiceProvider::FEATURE);
        ResponseService::noPermissionThenSendJson($permission);
    }
}
