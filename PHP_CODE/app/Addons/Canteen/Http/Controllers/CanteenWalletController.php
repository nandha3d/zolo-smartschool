<?php

namespace App\Addons\Canteen\Http\Controllers;

use App\Addons\Canteen\CanteenServiceProvider;
use App\Addons\Canteen\Models\CanteenCategory;
use App\Addons\Canteen\Models\CanteenInventoryItem;
use App\Addons\Canteen\Models\CanteenItem;
use App\Addons\Canteen\Models\CanteenMealPlan;
use App\Addons\Canteen\Models\CanteenMealSubscription;
use App\Addons\Canteen\Models\CanteenWallet;
use App\Addons\Canteen\Models\CanteenWalletTransaction;
use App\Addons\Canteen\Services\CanteenService;
use App\Addons\Concerns\RespondsToBootstrapTable;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Wallets (top-ups, limits, allergies, RFID cards), the ledger, inventory and meal plans. */
class CanteenWalletController extends Controller
{
    use RespondsToBootstrapTable;

    public function __construct(private CanteenService $canteen)
    {
    }

    public function index()
    {
        ResponseService::noFeatureThenRedirect(CanteenServiceProvider::FEATURE);
        ResponseService::noPermissionThenRedirect('canteen-wallet');
        $categories = CanteenCategory::orderBy('name')->get(['id', 'name']);
        $allergens = CanteenItem::ALLERGENS;
        return view('canteen::wallets', compact('categories', 'allergens'));
    }

    public function list()
    {
        $this->guard('canteen-wallet');
        $query = CanteenWallet::with('user:id,first_name,last_name,email,mobile')
            ->when(request('status') === 'low', fn($q) => $q->where('balance', '<', 50))
            ->when(request('status') === 'locked', fn($q) => $q->where('is_locked', true));
        return $this->tableResponse($query, fn(CanteenWallet $w) => $this->canteen->walletSummary($w),
            ['rfid_card', 'user.first_name', 'user.last_name', 'user.email'], ['id', 'balance']);
    }

    /** Look someone up (card, admission no., email …); creates their wallet on first use. */
    public function lookup(Request $request): JsonResponse
    {
        $this->guard('canteen-wallet');
        $request->validate(['code' => 'required|string|max:64']);
        $wallet = $this->canteen->findWallet($request->code);
        if (!$wallet) {
            return response()->json(['error' => true, 'message' => trans('No student or staff member matches.')], 404);
        }
        return response()->json(['error' => false, 'wallet' => $this->canteen->walletSummary($wallet)]);
    }

    public function topUp(Request $request, $id): JsonResponse
    {
        $this->guard('canteen-wallet');
        $data = $request->validate([
            'amount' => 'required|numeric|min:1|max:100000',
            'payment_mode' => ['required', Rule::in(['Cash', 'Card', 'Online', 'Cheque'])],
            'reference' => 'nullable|string|max:64',
        ]);
        return $this->run(function () use ($id, $data) {
            $txn = $this->canteen->topUp(CanteenWallet::findOrFail($id), (float) $data['amount'], $data['payment_mode'], $data['reference'] ?? null);
            return trans('Wallet topped up. New balance :balance.', ['balance' => number_format((float) $txn->balance_after, 2)]);
        });
    }

    public function adjust(Request $request, $id): JsonResponse
    {
        $this->guard('canteen-edit');
        $data = $request->validate(['amount' => 'required|numeric|between:-100000,100000|not_in:0', 'note' => 'required|string|max:191']);
        return $this->run(function () use ($id, $data) {
            $txn = $this->canteen->adjust(CanteenWallet::findOrFail($id), (float) $data['amount'], $data['note']);
            return trans('Balance adjusted to :balance.', ['balance' => number_format((float) $txn->balance_after, 2)]);
        });
    }

    public function updateControls(Request $request, $id): JsonResponse
    {
        $this->guard('canteen-wallet');
        $wallet = CanteenWallet::findOrFail($id);
        $data = $request->validate([
            'rfid_card' => ['nullable', 'string', 'max:64', Rule::unique('canteen_wallets', 'rfid_card')->ignore($wallet->id)],
            'daily_limit' => 'nullable|numeric|min:0|max:100000',
            'blocked_category_ids' => 'nullable|array',
            'blocked_category_ids.*' => 'integer',
            'allergens' => 'nullable|array',
            'allergens.*' => ['string', Rule::in(CanteenItem::ALLERGENS)],
        ]);
        $wallet->update([
            'rfid_card' => $data['rfid_card'] ?? null ?: null,
            'daily_limit' => isset($data['daily_limit']) && $data['daily_limit'] !== '' ? $data['daily_limit'] : null,
            'blocked_category_ids' => array_values(array_map('intval', $data['blocked_category_ids'] ?? [])),
            'allergens' => array_values($data['allergens'] ?? []),
            'is_locked' => $request->boolean('is_locked'),
        ]);
        return response()->json(['error' => false, 'message' => trans('Wallet settings saved.'), 'wallet' => $this->canteen->walletSummary($wallet->fresh())]);
    }

    public function transactions()
    {
        $this->guard('canteen-wallet');
        $query = CanteenWalletTransaction::with('wallet.user:id,first_name,last_name')
            ->when(request('wallet_id'), fn($q, $id) => $q->where('wallet_id', $id))
            ->when(request('type'), fn($q, $t) => $q->where('type', $t));
        return $this->tableResponse($query, fn(CanteenWalletTransaction $t) => [
            'id' => $t->id,
            'name' => $t->wallet?->user?->full_name,
            'type' => $t->type,
            'amount' => $t->amount,
            'balance_after' => $t->balance_after,
            'payment_mode' => $t->payment_mode,
            'reference' => $t->reference,
            'note' => $t->note,
            'created_at' => $t->getRawOriginal('created_at'),
        ], ['reference'], ['id', 'amount']);
    }

    // ------------------------------------------------------------ inventory

    public function inventory()
    {
        ResponseService::noFeatureThenRedirect(CanteenServiceProvider::FEATURE);
        ResponseService::noPermissionThenRedirect('canteen-list');
        $items = CanteenInventoryItem::orderBy('name')->get();
        return view('canteen::inventory', compact('items'));
    }

    public function storeInventory(Request $request): JsonResponse
    {
        $this->guard('canteen-create');
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'unit' => ['required', Rule::in(['kg', 'g', 'litres', 'ml', 'pcs', 'packets', 'dozen'])],
            'current_stock' => 'nullable|numeric|min:0',
            'reorder_level' => 'nullable|numeric|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'supplier' => 'nullable|string|max:191',
        ]);
        $opening = (float) ($data['current_stock'] ?? 0);
        $item = CanteenInventoryItem::create(['current_stock' => 0, 'reorder_level' => $data['reorder_level'] ?? 0] + $data);
        if ($opening > 0) {
            $item->update(['current_stock' => 0]);
            $this->canteen->moveStock($item, $opening, 'Purchase', 'Opening stock');
        }
        return response()->json(['error' => false, 'message' => trans('Data Stored Successfully')]);
    }

    public function moveInventory(Request $request, $id): JsonResponse
    {
        $this->guard('canteen-edit');
        $data = $request->validate([
            'quantity' => 'required|numeric|min:0.001',
            'reason' => ['required', Rule::in(['Purchase', 'Usage', 'Wastage', 'Correction'])],
            'direction' => 'nullable|in:in,out',
            'note' => 'nullable|string|max:191',
        ]);
        $sign = $data['reason'] === 'Purchase' || ($data['reason'] === 'Correction' && ($data['direction'] ?? 'in') === 'in') ? 1 : -1;
        return $this->run(function () use ($id, $data, $sign) {
            $item = $this->canteen->moveStock(CanteenInventoryItem::findOrFail($id), $sign * (float) $data['quantity'], $data['reason'], $data['note'] ?? null);
            return trans('Stock now :stock :unit.', ['stock' => rtrim(rtrim(number_format((float) $item->current_stock, 3), '0'), '.'), 'unit' => $item->unit]);
        });
    }

    public function destroyInventory($id): JsonResponse
    {
        $this->guard('canteen-delete');
        CanteenInventoryItem::findOrFail($id)->delete();
        return response()->json(['error' => false, 'message' => trans('Data Deleted Successfully')]);
    }

    // ------------------------------------------------------------ meal plans

    public function plans()
    {
        ResponseService::noFeatureThenRedirect(CanteenServiceProvider::FEATURE);
        ResponseService::noPermissionThenRedirect('canteen-list');
        $plans = CanteenMealPlan::orderBy('name')->get();
        $subscriptions = CanteenMealSubscription::with('plan', 'user:id,first_name,last_name')->latest('id')->limit(100)->get();
        return view('canteen::plans', compact('plans', 'subscriptions'));
    }

    public function storePlan(Request $request): JsonResponse
    {
        $this->guard('canteen-create');
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'price' => 'required|numeric|min:0|max:1000000',
            'duration_days' => 'required|integer|min:1|max:366',
            'description' => 'nullable|string|max:191',
        ]);
        CanteenMealPlan::create($data + ['is_active' => true]);
        return response()->json(['error' => false, 'message' => trans('Data Stored Successfully')]);
    }

    public function togglePlan($id): JsonResponse
    {
        $this->guard('canteen-edit');
        $plan = CanteenMealPlan::findOrFail($id);
        $plan->update(['is_active' => !$plan->is_active]);
        return response()->json(['error' => false, 'message' => trans('Data Updated Successfully')]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $this->guard('canteen-wallet');
        $data = $request->validate([
            'plan_id' => 'required|integer',
            'code' => 'required|string|max:64',
            'payment_mode' => ['required', Rule::in(['Wallet', 'Cash'])],
            'start_date' => 'nullable|date',
        ]);
        $wallet = $this->canteen->findWallet($data['code']);
        if (!$wallet) {
            return response()->json(['error' => true, 'message' => trans('No student or staff member matches.')], 404);
        }
        return $this->run(function () use ($data, $wallet) {
            $sub = $this->canteen->subscribe(CanteenMealPlan::findOrFail($data['plan_id']), User::findOrFail($wallet->user_id), $data['payment_mode'], $data['start_date'] ?? null);
            return trans('Subscribed until :date.', ['date' => $sub->end_date->toDateString()]);
        });
    }

    public function cancelSubscription($id): JsonResponse
    {
        $this->guard('canteen-wallet');
        CanteenMealSubscription::findOrFail($id)->update(['status' => 'Cancelled']);
        return response()->json(['error' => false, 'message' => trans('Subscription cancelled.')]);
    }

    private function guard(string $permission): void
    {
        ResponseService::noFeatureThenSendJson(CanteenServiceProvider::FEATURE);
        ResponseService::noPermissionThenSendJson($permission);
    }

    private function run(callable $action): JsonResponse
    {
        try {
            return response()->json(['error' => false, 'message' => $action()]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 422);
        }
    }
}
