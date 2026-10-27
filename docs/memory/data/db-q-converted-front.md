---
name: db-q-converted-front
description: Front-end files converted to db_q() (shopping cart, form item view, view order, email preferences, watchers, affiliate sign-up): PR, clauses with values, what stayed raw, guard test.
---

## Which front-end files are converted, and what stayed raw?
- PR #2300: `get_shopping_cart.php` 13, `get_form_item_view.php` 13, `get_view_order_screen_content.php` 15, `email_preferences.php` 17, `add_or_remove_watcher.php` 15, `affiliate_sign_up_form.php` 13 calls with values, `(string)` where the SQL quoted, also where an id, a session order id or a config constant (`AFFILIATE_CONTACT_GROUP_ID`, `REGISTRATION_CONTACT_GROUP_ID`, `USER_ID`) was written unescaped into quotes (digits, so the text is the same).
- `(int)` only in the affiliate contact UPDATE, which wrote `user = $user_id` and `WHERE id = $contact_id` bare; that branch runs only with a contact found, so both are database ids.
- A cast value with `??` keeps its parentheses: `(string) ($_SESSION[...] ?? '')`; without them the cast binds first and a missing key warns.
- Clauses with values carry them beside the SQL (`array_merge()`): the form item view's `$where` (form id or reference code, `$where_values`), the email preferences' `$sql_update_username` (`$sql_update_username_values`) and its opt-in column (`$opt_in_posted`). Clauses without values stay inline: `$sql_field_selects` (column names from `get_standard_fields_for_view()`, no `?`), `$sql_office_use_only`, the integer-only `$sql_update_user`.
- Raw stay the two queries of `email_preferences.php` without values. The `db()` / `db_item()` / `db_value()` calls with `e()` / `escape()` are untouched.
- A value posted as an array was a TypeError in `escape()` under PHP 8; `(string)` now gives `'Array'`, which matches no row. The earlier conversions behave the same way.

## How was it checked?
- Each old and new query was rendered with a marker per value and compared; the `array_merge()` queries were run with values holding `'` and `?`. `tests/db_q_front_test.php` guards the files, counts each literal value list against its `?` and binds the clause cases.

Related: docs/memory/data/db-q-converted-files.md (conversion rules), docs/memory/data/db-q-converted-commerce.md
