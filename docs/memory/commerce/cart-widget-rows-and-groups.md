---
name: cart-widget-rows-and-groups
description: Cart widget row types (donation, offer gift, normal), pending offers own-form rule, tax/shipping notice, and recipient/recurring group heading bands with their sorting.
---

## Which cart row types exist? (legacy `get_shopping_cart.php:770-815` branching)
| Row | Quantity control | Token |
|---|---|---|
| `selection_type='donation'` | amount box with currency prefix (`donations[<id>]`) | `__item_is_donation` |
| `added_by_offer=1` | plain number, not editable | `__item_added_by_offer` |
| normal | qty stepper | none |
- Donation amount in `cart_action.php`: thousands separator stripped, divided by the rate, converted to kuruş with `round()`; a line <= 0 is deleted.

## How are pending offers (campaign gifts) drawn?
- `_pg_render_pending_offers()`, section binding `pending_offers`, token `__pending_offers`.
- It has its OWN `<form>`: inside the cart update form the HTML parser drops the inner form and "Ekle" submits the wrong form.
- Field names must match legacy exactly (`add_pending_offers()` parses with substr/explode).

## What does the tax/shipping notice say?
- `_pg_cart_tax_shipping_notice()` -> `__tax_shipping_notice`. It states only what is truly unknown; returns empty when neither applies.

## How are group heading bands built?
- The cart loop is a flat list (no legacy nested blocks). Grouping uses "mark the first row of the run": rows are sorted first, then the loop keeps a cursor to find the group head and fills the band token only on that row.
| Group | Sorting | Band token |
|---|---|---|
| Recipient | SQL `ORDER BY ship_to_id, id` | `__item_recipient_heading` |
| Recurring | PHP `usort` (recipient -> today/recurring -> id) | `__item_recurring_heading` |
- The recurring split cannot be sorted in SQL: `is_recurring` depends on a date comparison (a row with `recurring_start_date == today` still falls under "today's charges", legacy `:1155`).
- Recipient band shows under legacy's three conditions: shipping on + multi-recipient mode + `ship_to_id > 0`. `ship_to_id = 0` means "myself": nothing to distinguish, no band.

Related: docs/memory/commerce/cart-widget-payment-plan-and-gates.md
