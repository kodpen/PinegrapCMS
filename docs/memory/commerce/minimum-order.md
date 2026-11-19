---
name: minimum-order
description: A contact group's minimum order value (WS4): the one setting, the highest of a customer's groups, guests, the amount compared, the refusal at submit and the cart's warning
module: minimum_order
---

## Where is a group's minimum kept?
- (WS4, #9987) One `config_text` row, `contact_group_minimum_order`: a JSON object of contact group id => whole minor units of the base currency (`pg_minimum_order_read()`, `pg_minimum_order_encode()`). No column, no "Wholesale" switch (architect, agent channel message 6590).
- A deleted group's entry is ignored when read (`pg_minimum_order_map()` keeps only ids still in `contact_groups`); a group without an entry orders as before (J10).
- Saved with `pg_minimum_order_save()`: written only while the text still holds what was read (`pg_config_text_claim()`), three tries, so two groups saved together keep both amounts; 0 removes the group, an empty map the row.

## Where is it set?
- Contact group edit screen (`edit_contact_group.php`), card "Minimum Order", field "Minimum order value" in the base currency with the money mask; empty is none, a negative amount is refused. Drawn and saved only for a person with the shop's rights while the shop is on (`pg_minimum_order_may_set()`: role below 3 or `manage_ecommerce`). The create screen has no field: a minimum is set once the group exists.

## Who must reach it?
- A signed-in customer, by their contact (`USER_CONTACT_ID`), not while staff look at the site as somebody else (`pg_minimum_order_customer()`). In several groups with a minimum, the highest applies (`pg_minimum_order_for_groups()`). A guest matches none, even when the order's contact is in a group.

## What amount is compared?
- The goods: `get_order_subtotal()` (price times quantity of the active lines, recurring lines that start later included), before discounts, tax and shipping, the same in the cart and at submit.
- `pg_minimum_order_check($contact_id, $amount, $rate, $context)` takes the amount in the order's currency with its rate and turns it into the base currency with `round($amount / $rate)` (`pg_minimum_order_base_amount()`), never `(int)`. Prices are kept in the base currency, so both callers pass rate 1. Equal to the minimum passes.

## What does the customer see?
- Cart: a warning in the cart's messages while the cart has lines, in the designer cart widget (`includes/fn/widgets_cart.php`, before its messages are drawn) and the classic cart (`get_shopping_cart.php`, whose system layout now prints warnings between errors and notices): "Orders from your account start at X. Add Y more to place your order."
- Both work it out after the page refreshes the lines (quantity and inventory checks, `update_order_item_prices()`, `apply_offers_to_cart()`), so it reads the prices the page shows. The classic cart once checked before that refresh (#10196, fixed in #10199): after signing in to a member price or a group offer the page showed a Subtotal below the minimum, no warning, and submit refused the order.
- Submit (`submit_order.php`): one refusal before the pre-save hook, for the express order and the order preview alike: "Sorry, the order was not accepted: orders from your account start at X, and Y is still missing." It is logged. A payment's return is not refused (the payment may already be taken). Amounts show in the visitor's currency (`pg_visitor_money()`).

## Which test guards it?
- `tests/minimum_order_test.php`: the setting, a deleted group, one group, two groups (the highest), a guest, the limit itself, another currency, no minimum, the money box, the wiring.
