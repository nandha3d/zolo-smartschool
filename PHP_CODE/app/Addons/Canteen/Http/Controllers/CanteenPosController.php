<?php

namespace App\Addons\Canteen\Http\Controllers;

use App\Addons\Canteen\CanteenServiceProvider;
use App\Addons\Canteen\Models\CanteenCategory;
use App\Addons\Canteen\Models\CanteenItem;
use App\Addons\Canteen\Models\CanteenOrder;
use App\Addons\Canteen\Models\CanteenWallet;
use App\Addons\Canteen\Services\CanteenService;
use App\Addons\Concerns\RespondsToBootstrapTable;
use App\Http\Controllers\Controller;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Touch POS, the kitchen board and the order register. */
class CanteenPosController extends Controller
{
    use RespondsToBootstrapTable;

    public function __construct(private CanteenService $canteen)
    {
    }

    public function pos()
    {
        ResponseService::noFeatureThenRedirect(CanteenServiceProvider::FEATURE);
        ResponseService::noPermissionThenRedirect('canteen-pos');
        $categories = CanteenCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
        $items = CanteenItem::orderBy('name')->get()->map(fn(CanteenItem $i) => [
            'id' => $i->id,
            'name' => $i->name,
            'code' => $i->code,
            'category_id' => $i->category_id,
            'price' => (float) $i->price,
            'dietary_type' => $i->dietary_type,
            'allergens' => $i->allergens ?? [],
            'available' => $i->is_available && (!$i->track_stock || $i->stock_qty > 0),
            'stock' => $i->track_stock ? $i->stock_qty : null,
            'image' => $i->image ? url(Storage::url($i->image)) : null,
        ]);
        return view('canteen::pos', compact('categories', 'items'));
    }

    public function customer(Request $request): JsonResponse
    {
        $this->guard('canteen-pos');
        $request->validate(['code' => 'required|string|max:64']);
        $wallet = $this->canteen->findWallet($request->code);
        if (!$wallet) {
            return response()->json(['error' => true, 'message' => trans('No student or staff member matches this card.')], 404);
        }
        return response()->json(['error' => false, 'wallet' => $this->canteen->walletSummary($wallet)]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $this->guard('canteen-pos');
        $data = $request->validate([
            'wallet_id' => 'nullable|integer',
            'payment_mode' => ['required', Rule::in(['Wallet', 'Cash', 'Card'])],
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|integer',
            'lines.*.quantity' => 'required|integer|min:1|max:100',
            'notes' => 'nullable|string|max:191',
        ]);
        try {
            $wallet = !empty($data['wallet_id']) ? CanteenWallet::findOrFail($data['wallet_id']) : null;
            $order = $this->canteen->checkout($data['lines'], $data['payment_mode'], $wallet, 'Counter', null, $data['notes'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage(), 'allergen' => str_starts_with($e->getMessage(), 'ALLERGEN')], 422);
        }
        return response()->json([
            'error' => false,
            'message' => trans('Order #:token placed.', ['token' => $order->token_number]),
            'order' => $this->orderRow($order),
            'wallet' => $wallet ? $this->canteen->walletSummary($wallet->fresh()) : null,
        ]);
    }

    public function kitchen()
    {
        ResponseService::noFeatureThenRedirect(CanteenServiceProvider::FEATURE);
        ResponseService::noPermissionThenRedirect('canteen-pos');
        return view('canteen::kitchen');
    }

    public function kitchenOrders(): JsonResponse
    {
        $this->guard('canteen-pos');
        $orders = CanteenOrder::with('items', 'user:id,first_name,last_name')
            ->whereDate('service_date', Carbon::today())
            ->whereIn('status', ['Pending', 'Preparing', 'Ready'])
            ->orderBy('token_number')->get()->map(fn($o) => $this->orderRow($o));
        return response()->json(['error' => false, 'orders' => $orders]);
    }

    public function setStatus(Request $request, $id): JsonResponse
    {
        $this->guard('canteen-pos');
        $request->validate(['status' => ['required', Rule::in(['Preparing', 'Ready', 'Completed', 'Cancelled'])], 'reason' => 'nullable|string|max:120']);
        try {
            $order = CanteenOrder::findOrFail($id);
            $order = $request->status === 'Cancelled'
                ? $this->canteen->cancelOrder($order, $request->reason)
                : $this->canteen->advanceOrder($order, $request->status);
        } catch (RuntimeException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['error' => false, 'message' => trans('Order #:token is :status.', ['token' => $order->token_number, 'status' => $order->status])]);
    }

    public function orders()
    {
        ResponseService::noFeatureThenRedirect(CanteenServiceProvider::FEATURE);
        ResponseService::noPermissionThenRedirect('canteen-list');
        return view('canteen::orders');
    }

    public function ordersList()
    {
        $this->guard('canteen-list');
        $query = CanteenOrder::with('items', 'user:id,first_name,last_name')
            ->when(request('date'), fn($q, $d) => $q->whereDate('service_date', $d))
            ->when(request('status'), fn($q, $s) => $q->where('status', $s));
        return $this->tableResponse($query, fn(CanteenOrder $o) => $this->orderRow($o), ['order_number'], ['id', 'service_date', 'total_amount']);
    }

    private function orderRow(CanteenOrder $o): array
    {
        return [
            'id' => $o->id,
            'order_number' => $o->order_number,
            'token_number' => $o->token_number,
            'customer' => $o->user?->full_name ?? trim(($o->user?->first_name ?? '') . ' ' . ($o->user?->last_name ?? '')) ?: null,
            'items' => $o->items->map(fn($l) => ['name' => $l->name, 'quantity' => $l->quantity, 'subtotal' => $l->subtotal]),
            'items_text' => $o->items->map(fn($l) => $l->quantity . '× ' . $l->name)->implode(', '),
            'total_amount' => $o->total_amount,
            'payment_mode' => $o->payment_mode,
            'order_type' => $o->order_type,
            'status' => $o->status,
            'service_date' => $o->service_date?->toDateString(),
            'time' => $o->created_at ? Carbon::parse($o->getRawOriginal('created_at'))->format('H:i') : null,
            'notes' => $o->notes,
        ];
    }

    private function guard(string $permission): void
    {
        ResponseService::noFeatureThenSendJson(CanteenServiceProvider::FEATURE);
        ResponseService::noPermissionThenSendJson($permission);
    }
}
