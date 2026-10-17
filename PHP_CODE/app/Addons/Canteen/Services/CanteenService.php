<?php

namespace App\Addons\Canteen\Services;

use App\Addons\Canteen\Models\CanteenCategory;
use App\Addons\Canteen\Models\CanteenInventoryItem;
use App\Addons\Canteen\Models\CanteenInventoryMovement;
use App\Addons\Canteen\Models\CanteenItem;
use App\Addons\Canteen\Models\CanteenMealPlan;
use App\Addons\Canteen\Models\CanteenMealSubscription;
use App\Addons\Canteen\Models\CanteenOrder;
use App\Addons\Canteen\Models\CanteenOrderItem;
use App\Addons\Canteen\Models\CanteenWallet;
use App\Addons\Canteen\Models\CanteenWalletTransaction;
use App\Models\Students;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Money and safety rules for the canteen. Every balance change goes through
 * post(), which locks the wallet row and writes an immutable ledger entry carrying
 * the resulting balance, so the balance always equals the sum of its transactions.
 */
class CanteenService
{
    public function walletFor(User $user): CanteenWallet
    {
        return CanteenWallet::firstOrCreate(
            ['user_id' => $user->id],
            ['school_id' => $user->school_id ?? Auth::user()?->school_id, 'balance' => 0]
        );
    }

    /** RFID card, admission number, email, mobile or user id → that person's wallet. */
    public function findWallet(string $code): ?CanteenWallet
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        $wallet = CanteenWallet::where('rfid_card', $code)->first();
        if ($wallet) {
            return $wallet;
        }
        $userId = Students::where('admission_no', $code)->value('user_id');
        $user = $userId ? User::find($userId) : User::where('email', $code)->orWhere('mobile', $code)->first();
        if (!$user && ctype_digit($code)) {
            $user = User::find((int) $code);
        }
        if (!$user || $user->hasRole(['Guardian', 'Super Admin'])) {
            return null;
        }
        return $this->walletFor($user);
    }

    /** Canteen orders paid from the wallet today, net of refunds (meal-plan charges excluded). */
    public function spentToday(CanteenWallet $wallet): float
    {
        $net = (float) CanteenWalletTransaction::where('wallet_id', $wallet->id)
            ->whereIn('type', ['Purchase', 'Refund'])
            ->whereNotNull('order_id')
            ->whereDate('created_at', Carbon::today())
            ->sum('amount');
        return max(0.0, round(-$net, 2));
    }

    public function walletSummary(CanteenWallet $wallet): array
    {
        $wallet->loadMissing('user');
        $spent = $this->spentToday($wallet);
        $blocked = CanteenCategory::whereIn('id', $wallet->blocked_category_ids ?? [])->pluck('name');
        $class = null;
        if ($wallet->user?->hasRole('Student')) {
            $student = Students::with('class_section.class', 'class_section.section', 'class_section.medium')->where('user_id', $wallet->user_id)->first();
            $class = $student?->class_section?->full_name;
        }
        return [
            'id' => $wallet->id,
            'user_id' => $wallet->user_id,
            'name' => $wallet->user?->full_name,
            'class' => $class,
            'image' => $wallet->user?->image,
            'balance' => (float) $wallet->balance,
            'daily_limit' => $wallet->daily_limit !== null ? (float) $wallet->daily_limit : null,
            'spent_today' => round($spent, 2),
            'left_today' => $wallet->daily_limit !== null ? max(0, round((float) $wallet->daily_limit - $spent, 2)) : null,
            'allergens' => $wallet->allergens ?? [],
            'blocked_category_ids' => $wallet->blocked_category_ids ?? [],
            'blocked_categories' => $blocked,
            'is_locked' => $wallet->is_locked,
            'rfid_card' => $wallet->rfid_card,
        ];
    }

    /**
     * Ring up a sale.
     *
     * @param array<int, array{item_id: int, quantity: int}> $lines
     */
    public function checkout(array $lines, string $paymentMode, ?CanteenWallet $wallet = null, string $orderType = 'Counter', ?string $serviceDate = null, ?string $notes = null): CanteenOrder
    {
        return DB::transaction(function () use ($lines, $paymentMode, $wallet, $orderType, $serviceDate, $notes) {
            $quantities = [];
            foreach ($lines as $line) {
                $qty = (int) ($line['quantity'] ?? 0);
                if ($qty > 0) {
                    $quantities[(int) $line['item_id']] = ($quantities[(int) $line['item_id']] ?? 0) + $qty;
                }
            }
            if (!$quantities) {
                throw new RuntimeException('The order is empty.');
            }

            $items = CanteenItem::whereIn('id', array_keys($quantities))->lockForUpdate()->get()->keyBy('id');
            $total = 0.0;
            foreach ($quantities as $itemId => $qty) {
                $item = $items[$itemId] ?? null;
                if (!$item || !$item->is_available) {
                    throw new RuntimeException(($item?->name ?? 'An item') . ' is not available.');
                }
                if ($item->track_stock && $item->stock_qty < $qty) {
                    throw new RuntimeException("Only {$item->stock_qty} {$item->name} left.");
                }
                $total += (float) $item->price * $qty;
            }
            $total = round($total, 2);

            if ($paymentMode === 'Wallet' && !$wallet) {
                throw new RuntimeException('Identify the customer to pay by wallet.');
            }
            if ($wallet) {
                $wallet = CanteenWallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
                $this->assertSafe($wallet, $items, array_keys($quantities));
                if ($paymentMode === 'Wallet') {
                    if ($wallet->is_locked) {
                        throw new RuntimeException('This wallet is locked.');
                    }
                    if ((float) $wallet->balance < $total) {
                        throw new RuntimeException('Insufficient balance: ' . number_format((float) $wallet->balance, 2) . ' available, ' . number_format($total, 2) . ' needed.');
                    }
                    if ($wallet->daily_limit !== null && $this->spentToday($wallet) + $total > (float) $wallet->daily_limit + 0.001) {
                        throw new RuntimeException('Daily spending limit of ' . number_format((float) $wallet->daily_limit, 2) . ' would be exceeded (spent ' . number_format($this->spentToday($wallet), 2) . ' today).');
                    }
                }
            }

            $date = $serviceDate ? Carbon::parse($serviceDate) : Carbon::today();
            $token = (int) CanteenOrder::whereDate('service_date', $date)->lockForUpdate()->max('token_number') + 1;
            $order = CanteenOrder::create([
                'school_id' => $wallet?->school_id ?? Auth::user()?->school_id,
                'order_number' => 'CNT-' . $date->format('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5)),
                'token_number' => $token,
                'user_id' => $wallet?->user_id,
                'wallet_id' => $wallet?->id,
                'total_amount' => $total,
                'payment_mode' => $paymentMode,
                'order_type' => $orderType,
                'status' => 'Pending',
                'service_date' => $date,
                'served_by' => Auth::id(),
                'notes' => $notes,
            ]);

            foreach ($quantities as $itemId => $qty) {
                $item = $items[$itemId];
                CanteenOrderItem::create([
                    'order_id' => $order->id,
                    'item_id' => $item->id,
                    'name' => $item->name,
                    'quantity' => $qty,
                    'unit_price' => $item->price,
                    'subtotal' => round((float) $item->price * $qty, 2),
                ]);
                if ($item->track_stock) {
                    $item->decrement('stock_qty', $qty);
                }
            }

            if ($paymentMode === 'Wallet') {
                $this->post($wallet, 'Purchase', -$total, 'Wallet', $order->order_number, $order->id);
            }

            return $order->load('items');
        });
    }

    public function cancelOrder(CanteenOrder $order, ?string $reason = null): CanteenOrder
    {
        return DB::transaction(function () use ($order, $reason) {
            $order = CanteenOrder::with('items')->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (in_array($order->status, ['Completed', 'Cancelled'], true)) {
                throw new RuntimeException("This order is already {$order->status}.");
            }
            $order->update(['status' => 'Cancelled', 'notes' => trim(($order->notes ? $order->notes . ' · ' : '') . ($reason ?? 'Cancelled'))]);
            foreach ($order->items as $line) {
                CanteenItem::whereKey($line->item_id)->where('track_stock', true)->increment('stock_qty', $line->quantity);
            }
            if ($order->payment_mode === 'Wallet' && $order->wallet_id) {
                $wallet = CanteenWallet::whereKey($order->wallet_id)->lockForUpdate()->firstOrFail();
                $this->post($wallet, 'Refund', (float) $order->total_amount, 'Wallet', $order->order_number, $order->id, 'Order cancelled');
            }
            return $order;
        });
    }

    public function advanceOrder(CanteenOrder $order, string $status): CanteenOrder
    {
        $flow = ['Pending' => ['Preparing', 'Ready', 'Completed'], 'Preparing' => ['Ready', 'Completed'], 'Ready' => ['Completed']];
        if (!in_array($status, $flow[$order->status] ?? [], true)) {
            throw new RuntimeException("A {$order->status} order cannot move to {$status}.");
        }
        $order->update(['status' => $status]);
        return $order;
    }

    public function topUp(CanteenWallet $wallet, float $amount, string $mode, ?string $reference = null, ?string $note = null): CanteenWalletTransaction
    {
        if ($amount <= 0) {
            throw new RuntimeException('Top-up amount must be positive.');
        }
        return DB::transaction(function () use ($wallet, $amount, $mode, $reference, $note) {
            $wallet = CanteenWallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            return $this->post($wallet, 'Topup', $amount, $mode, $reference, null, $note);
        });
    }

    /** Correction by an administrator; may be negative but never takes the balance below zero. */
    public function adjust(CanteenWallet $wallet, float $amount, string $note): CanteenWalletTransaction
    {
        return DB::transaction(function () use ($wallet, $amount, $note) {
            $wallet = CanteenWallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            if ((float) $wallet->balance + $amount < 0) {
                throw new RuntimeException('An adjustment cannot make the balance negative.');
            }
            return $this->post($wallet, 'Adjustment', $amount, null, null, null, $note);
        });
    }

    public function subscribe(CanteenMealPlan $plan, User $user, string $mode, ?string $startDate = null): CanteenMealSubscription
    {
        if (!$plan->is_active) {
            throw new RuntimeException('This meal plan is not on offer.');
        }
        return DB::transaction(function () use ($plan, $user, $mode, $startDate) {
            $start = $startDate ? Carbon::parse($startDate) : Carbon::today();
            $overlap = CanteenMealSubscription::where('user_id', $user->id)->where('plan_id', $plan->id)->where('status', 'Active')
                ->whereDate('end_date', '>=', $start)->exists();
            if ($overlap) {
                throw new RuntimeException('There is already an active subscription to this plan.');
            }
            if ($mode === 'Wallet') {
                $wallet = CanteenWallet::whereKey($this->walletFor($user)->id)->lockForUpdate()->firstOrFail();
                if ($wallet->is_locked || (float) $wallet->balance < (float) $plan->price) {
                    throw new RuntimeException('Wallet balance is too low (or the wallet is locked).');
                }
                $this->post($wallet, 'Purchase', -(float) $plan->price, 'Wallet', 'PLAN-' . $plan->id, null, 'Meal plan: ' . $plan->name);
            }
            return CanteenMealSubscription::create([
                'school_id' => $plan->school_id,
                'plan_id' => $plan->id,
                'user_id' => $user->id,
                'start_date' => $start,
                'end_date' => $start->copy()->addDays(max(1, $plan->duration_days) - 1),
                'amount' => $plan->price,
                'payment_mode' => $mode,
                'status' => 'Active',
                'created_by' => Auth::id(),
            ]);
        });
    }

    public function moveStock(CanteenInventoryItem $item, float $change, string $reason, ?string $note = null): CanteenInventoryItem
    {
        return DB::transaction(function () use ($item, $change, $reason, $note) {
            $item = CanteenInventoryItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $after = round((float) $item->current_stock + $change, 3);
            if ($after < 0) {
                throw new RuntimeException('Stock cannot go below zero.');
            }
            $item->update(['current_stock' => $after]);
            CanteenInventoryMovement::create([
                'school_id' => $item->school_id,
                'inventory_item_id' => $item->id,
                'quantity_change' => $change,
                'stock_after' => $after,
                'reason' => $reason,
                'note' => $note,
                'created_by' => Auth::id(),
            ]);
            return $item;
        });
    }

    /** Allergen and parental category blocks apply whatever the payment mode. */
    private function assertSafe(CanteenWallet $wallet, $items, array $itemIds): void
    {
        $allergies = array_map('strtolower', $wallet->allergens ?? []);
        $blocked = array_map('intval', $wallet->blocked_category_ids ?? []);
        foreach ($itemIds as $itemId) {
            $item = $items[$itemId];
            $hits = array_values(array_intersect($allergies, array_map('strtolower', $item->allergens ?? [])));
            if ($hits) {
                throw new RuntimeException("ALLERGEN HAZARD: {$item->name} contains " . implode(', ', $hits) . ' and this student has a registered allergy. Item blocked.');
            }
            if ($item->category_id && in_array((int) $item->category_id, $blocked, true)) {
                throw new RuntimeException("{$item->name} is in a category this student's guardian has blocked.");
            }
        }
    }

    private function post(CanteenWallet $lockedWallet, string $type, float $amount, ?string $mode, ?string $reference, ?int $orderId = null, ?string $note = null): CanteenWalletTransaction
    {
        $balance = round((float) $lockedWallet->balance + $amount, 2);
        $lockedWallet->update(['balance' => $balance]);
        return CanteenWalletTransaction::create([
            'school_id' => $lockedWallet->school_id,
            'wallet_id' => $lockedWallet->id,
            'type' => $type,
            'amount' => round($amount, 2),
            'balance_after' => $balance,
            'payment_mode' => $mode,
            'reference' => $reference,
            'order_id' => $orderId,
            'created_by' => Auth::id(),
            'note' => $note,
        ]);
    }
}
