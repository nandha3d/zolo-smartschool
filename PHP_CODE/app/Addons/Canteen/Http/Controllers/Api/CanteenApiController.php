<?php

namespace App\Addons\Canteen\Http\Controllers\Api;

use App\Addons\Canteen\CanteenServiceProvider;
use App\Addons\Canteen\Models\CanteenCategory;
use App\Addons\Canteen\Models\CanteenItem;
use App\Addons\Canteen\Models\CanteenOrder;
use App\Addons\Canteen\Models\CanteenWalletTransaction;
use App\Addons\Canteen\Services\CanteenService;
use App\Http\Controllers\Controller;
use App\Models\Students;
use App\Models\User;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Student, staff and parent apps: wallet balance and history, what was eaten, the
 * menu, pre-orders, and — for guardians — spending limits, blocked categories and
 * allergies on their child's wallet. Top-ups are taken at the counter.
 */
class CanteenApiController extends Controller
{
    public function __construct(private CanteenService $canteen)
    {
    }

    public function wallet(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(CanteenServiceProvider::FEATURE);
        $wallet = $this->canteen->walletFor($this->subject($request));
        $data = $this->canteen->walletSummary($wallet);
        $data['transactions'] = CanteenWalletTransaction::where('wallet_id', $wallet->id)->latest('id')->limit(30)
            ->get(['id', 'type', 'amount', 'balance_after', 'reference', 'note', 'created_at']);
        $data['orders'] = CanteenOrder::with('items')->where('wallet_id', $wallet->id)->latest('id')->limit(20)->get()
            ->map(fn($o) => [
                'id' => $o->id,
                'token_number' => $o->token_number,
                'date' => $o->service_date?->toDateString(),
                'status' => $o->status,
                'total' => $o->total_amount,
                'type' => $o->order_type,
                'items' => $o->items->map(fn($l) => ['name' => $l->name, 'quantity' => $l->quantity, 'subtotal' => $l->subtotal]),
            ]);
        $data['categories'] = CanteenCategory::orderBy('name')->get(['id', 'name']);
        $data['allergen_options'] = CanteenItem::ALLERGENS;
        return response()->json(['error' => false, 'data' => $data]);
    }

    /** Guardians (and staff for themselves) set the daily cap, blocked categories and allergies. */
    public function controls(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(CanteenServiceProvider::FEATURE);
        $user = Auth::user();
        if ($user->hasRole('Student')) {
            return response()->json(['error' => true, 'message' => trans('Ask your guardian to change these settings.')], 403);
        }
        $data = $request->validate([
            'daily_limit' => 'nullable|numeric|min:0|max:100000',
            'blocked_category_ids' => 'nullable|array',
            'blocked_category_ids.*' => 'integer',
            'allergens' => 'nullable|array',
            'allergens.*' => ['string', Rule::in(CanteenItem::ALLERGENS)],
        ]);
        $wallet = $this->canteen->walletFor($this->subject($request));
        $wallet->update([
            'daily_limit' => $request->filled('daily_limit') ? $data['daily_limit'] : null,
            'blocked_category_ids' => array_values(array_map('intval', $data['blocked_category_ids'] ?? [])),
            'allergens' => array_values($data['allergens'] ?? []),
        ]);
        return response()->json(['error' => false, 'message' => trans('Saved.'), 'data' => $this->canteen->walletSummary($wallet->fresh())]);
    }

    public function menu(): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(CanteenServiceProvider::FEATURE);
        $items = CanteenItem::with('category:id,name')->where('is_available', true)->orderBy('name')->get()->map(fn($i) => [
            'id' => $i->id,
            'name' => $i->name,
            'category' => $i->category?->name,
            'category_id' => $i->category_id,
            'price' => $i->price,
            'dietary_type' => $i->dietary_type,
            'allergens' => $i->allergens ?? [],
            'calories' => $i->calories,
            'image' => $i->image ? url(Storage::url($i->image)) : null,
        ]);
        return response()->json(['error' => false, 'data' => $items]);
    }

    /** Pre-book a meal for today or a later date, paid from the wallet now. */
    public function preOrder(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(CanteenServiceProvider::FEATURE);
        $data = $request->validate([
            'service_date' => 'required|date|after_or_equal:today|before:+15 days',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|integer',
            'lines.*.quantity' => 'required|integer|min:1|max:10',
            'notes' => 'nullable|string|max:191',
        ]);
        $wallet = $this->canteen->walletFor($this->subject($request));
        try {
            $order = $this->canteen->checkout($data['lines'], 'Wallet', $wallet, 'Pre_Order', $data['service_date'], $data['notes'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['error' => false, 'message' => trans('Pre-order placed. Token :token on :date.', ['token' => $order->token_number, 'date' => $order->service_date->toDateString()])]);
    }

    /** The calling user, or a guardian's own child given by child_id. */
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
