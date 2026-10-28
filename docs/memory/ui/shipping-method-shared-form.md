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

## How are the start and end times handled (#2059)?
- A new method opens today at `12:00 AM` in the site's date order (`date(get_date_format_code() . '/Y')`), as the end time's 2099 default already did. The month-first `date('n/j/Y')` read day-first was day 10 of month 27 (a strict SQL mode refused the query, a lenient one stored `0000-00-00`) or, on days 1-12, a swapped date months away: the method is filtered with `start_time <= NOW()`, so it stayed out of checkout until then (PR #2624).
- A post is saved only when both times are a date in the site's order plus a 12- or 24-hour time: `pg_shipping_method_form_date_time_column()` gives the `Y-m-d H:i` value or null (swapped order, impossible day, ISO date, blank, no time). `pg_shipping_method_form_errors()` names the field; `pg_shipping_method_form_columns()` ends on the error screen with it before the screen's query. The edit screen still clears the zones and excluded dates before that check, so an invalid post loses them until the check runs before those deletes.
- A stored zero date is shown empty (`pg_shipping_method_form_date_time_value()`), not as `0/0/0000 12:00 AM`; the two inputs have no `maxlength` (the value is 19 characters).
- The help text under the cut-off and end-of-day time fields follows `TIME_FORMAT` (`pg_shipping_method_form_time_help()`): `h:mm AM/PM` for `twelve_hours`, else `HH:mm`.
