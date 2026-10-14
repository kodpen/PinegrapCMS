---
name: cart-widget-payment-plan-and-gates
description: Who writes cart recurring payment plan columns, the Messages-node-before-loop liveform snapshot rule, and the double gate for staff-only offline payment checkbox.
---

## Who writes the recurring payment plan on cart lines? (2026-09-16)
- Columns `recurring_payment_period` / `recurring_number_of_payments` / `recurring_start_date` are written ONLY by the cart update (`add_order_item()` does not).
- In legacy the visitor reaches checkout through that POST; the widget's "Ödemeye Geç" is a plain link, so the renderer writes a recurring row's empty plan with product defaults on first display (`_pg_cart_recurring_defaults`), and `cart_action.php` writes every recurring row on each update: what the customer submitted if editable (legacy field names, gateway rules), else the default. A rejected row keeps its stored plan.
- Tokens: `__item_recurring_schedule_html` (appended to line end if not placed), `__item_recurring_editable`, `__item_recurring_start_date`.

## Why do row builders read `$_pg_cart_lf_snapshot` and not the liveform?
- The cart renderer draws the Messages node BEFORE the row loop (static tree ~568, loop ~650). The node consumes the `shopping_cart` form; code in the loop calling `$_pg_cart_form_lf->get_field_value()` reads an already deleted form and finds nothing (the gift-card block did this).
- Row builders read `$_pg_cart_lf_snapshot` (`pg_liveform_snapshot`, a copy taken before the tree is drawn). A new row block needing the liveform takes the same copy, not the liveform object.
- This is the cart form of the membership-widget rule "read first, then add the Messages node".

## Staff-only controls are gated in two places
- `__offline_payment_checkbox` is invisible to customers (role < 3 or `set_offline_payment`). The gate is checked separately at render AND on the `cart_action.php` POST side.
- The POST check is mandatory: the checkbox is only HTML; a visitor could POST `offline_payment_allowed=1` and complete an order without paying. Hiding at render time is not an authorization check.

Related: docs/memory/commerce/cart-widget-rows-and-groups.md
