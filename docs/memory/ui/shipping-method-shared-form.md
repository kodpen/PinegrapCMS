---
name: shipping-method-shared-form
description: The shipping method create and edit screens share includes/forms/shipping_method_form.php
---

## Where is the shipping method form?
- `includes/forms/shipping_method_form.php` (gate `PG_FUNCTIONS_DIR`), PR #2366. `add_shipping_method.php` and `edit_shipping_method.php` stay at the root as thin wrappers.
- The cards are filled from a values array: `pg_shipping_method_form_new_values()` (a new method) or `pg_shipping_method_form_row_values($row, $dates)` (the stored row). Text values are raw (the cards escape them), switches booleans, `service` null when the picker keeps its default.
- A post is saved from `pg_shipping_method_form_columns($post)` (column => string, Monday-first day order; the screen adds `user`); the zones and excluded dates are written by `pg_shipping_method_save_links()`.

## What stays in the screens?
- Edit: the not-found guard, the record load, delete (also offer actions, cut-offs, ship date adjustments) and clearing zones and excluded dates before both delete and save.

## Which rules live there?
- Amounts: `pg_shipping_method_amount_cents()` (either separator, rounded, blank or text is 0). Variable base rates: valid pairs only (rate ≥ 0, subtotal > 0), sorted by subtotal, stored from `base_rate_2`; none valid means the switch is saved off. FedEx and no service save `realtime_rate` 0. Excluded dates: valid only, unique, sorted. Tests: `tests/shipping_method_form_test.php`.
