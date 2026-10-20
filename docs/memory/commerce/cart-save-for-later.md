---
name: cart-save-for-later
description: Opt-in Save for Later: cart_action.php endpoint, pg_order_item_active_sql() in every cart and checkout reader, what still counts a saved line, lines dropped when the order is placed (#732).
---

## How does Save for Later work? (opt-in, 2026.1.28)
- Define `ECOMMERCE_SAVE_FOR_LATER` (bool, off when undefined). Upgrade `2026.1.28` adds `order_items.saved_for_later` (TINYINT, default 0), `saved_at` and index `idx_saved_for_later`.
- `cart_action.php`, only with the define on: `submit_save_for_later=1` / `submit_restore_saved=1` + `save_item_id=<int>`. Token-checked like every cart form; the line must belong to the session's order; a missing column makes it a no-op (`pg_schema_has()`).
- After a save or a restore, a complete recipient gets its shipping worked out again (`update_shipping_cost_for_ship_to()`), as after a quantity change.
- No widget or section draws the saved list yet; a designer builds it with custom POST forms.

## Which readers leave a saved line out?
- Every cart and checkout reader appends `pg_order_item_active_sql($alias)` (`includes/fn/ecommerce.php`): ` AND (<alias>.saved_for_later = 0)`, empty before the column exists. It does not look at the define: lines saved before the feature was switched off stay out, as ERP, Paraşüt, returns and `view_order.php` already did.
- Users: designer cart and express order widgets (rows, tax sums, empty check, applied offers, cart link), `add_to_cart.php` totals, `get_order_subtotal()`, `pg_order_subtotal_charged_today()`, the offer helpers (`pg_offer_cart_quantity()`, `pg_offer_cheapest_item_id()`, `check_for_products_in_cart()`, `validate_offer()`, `pg_offer_usage_counts()`, the gift's recipient and paid line), the recipient lists of `apply_offers_to_cart()` and `get_best_offer_id()`, `check_inventory()`, `check_quantity()`, `check_reservations()`, `get_valid_zones()`, `shipping.php`, `express_order.php`, `submit_order.php` (charged lines, required and free item checks, shippable counts), `pg_payment_order_ship_to()`. `add_order_item()` merges a new add into an active line only.

## Which code still counts a saved line, on purpose?
- Per-line writers: `update_order_item_prices()`, `update_order_item_taxes()`, the offer discount reset. A restored line comes back with current figures.
- The offer gift review and every "already applied" check in `apply_offers_to_cart()` and `add_pending_offers()`: a saved gift line still holds its offer, so a save and a restore cannot add a second gift.
- Legacy pages (`get_shopping_cart.php`, `get_express_order.php`, `get_order_preview.php`) are not changed (recorded decision) and list a saved line. The order is still charged without it: `submit_order.php` only rejects a server total above the form's.

## What happens to saved lines when the order is placed?
- Right after `status = 'complete'`, `submit_order.php` calls `pg_order_drop_saved_lines()`: the lines go with their `order_item_gift_cards` and product `form_data` rows; recipients and shipping stay as charged. The shopper cannot reach them any more (the session drops the order id), and the receipt, print-outs, the API and reorders read the lines without the flag.

## What was the symptom before #732?
- The designer express order hid the saved line but `submit_order.php` charged it: "the total has changed" on every try, or the hidden item charged where the check is skipped (Iyzipay, 2+ installments). The cart widget still listed it; tax, offer thresholds, stock and reservation checks counted it.
