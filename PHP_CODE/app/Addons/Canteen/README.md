# Canteen & Cashless Wallet

Menu with dietary and allergen tags, a touch POS that identifies students by RFID card, a
wallet with an immutable ledger, guardian controls (daily limit, blocked categories,
allergies), a kitchen order board, app pre-orders, raw-material inventory and meal plans.

## Turning it on

```bash
php artisan migrate          # registers the "Canteen Management" feature (off by default)
php artisan migrate:school   # creates the canteen tables in every school database
```

A Super Admin adds **Canteen Management** to a package or sells it as an addon. The tenant
migration creates the `canteen-*` permissions and grants them to School Admin.

| Permission | Allows |
|---|---|
| `canteen-list` | dashboard, menu, orders, inventory, meal plans |
| `canteen-create` / `-edit` / `-delete` | menu, inventory, plans; `-edit` also wallet corrections |
| `canteen-pos` | the POS and the kitchen board |
| `canteen-wallet` | wallet top-ups, cards, limits and allergies; meal plan subscriptions |

## Money rules

- Every balance change (top-up, purchase, refund, correction, meal plan) locks the wallet row
  and writes a ledger entry with the resulting balance, so the balance always equals the sum of
  the wallet's transactions. Corrections can never take a balance below zero.
- Wallet checkout checks balance, the lock flag and the guardian's **daily limit** (canteen
  orders paid from the wallet today, net of refunds).
- Cancelling an order refunds a wallet payment and puts tracked stock back.

## Safety interlock

When the customer is identified (card or admission no.), items containing one of their
registered **allergens** are refused — on the POS (red modal) and again on the server, whatever
the payment method. Items in a **category the guardian has blocked** are refused the same way.

## Cards

Any USB RFID / barcode reader that types the card number works: assign the number to a wallet
on the Wallets page, then tap it at the POS.

## Mobile API (`school-code` header + Sanctum token; guardians add `child_id`)

| Method | Path | |
|---|---|---|
| GET | `api/canteen/wallet` | balance, limit, today's spend, ledger, orders with items |
| POST | `api/canteen/wallet/controls` | guardians / staff: `daily_limit`, `blocked_category_ids[]`, `allergens[]` (full replace) |
| GET | `api/canteen/menu` | available items with dietary type, allergens, calories |
| POST | `api/canteen/pre-order` | `service_date` (today … 14 days), `lines[][item_id, quantity]`, paid from the wallet |

## Not included

Online wallet top-up through the payment gateways — top-ups are taken at the counter (cash,
card, online transfer reference or cheque).
