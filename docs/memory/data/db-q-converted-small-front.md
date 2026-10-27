---
name: db-q-converted-small-front
description: Address book, e-mail preferences, form views, catalog detail, order form and other small front files converted to db_q(): special cases, what stayed raw, guard test.
---

## Which files are converted, and what stayed raw?
- PR (named below once open): `get_email_preferences.php` 6, `update_address_book.php` 5, `get_update_address_book.php` 2, `get_form_list_view.php` 3, `get_custom_form_confirmation_screen_content.php` 4, `get_catalog_detail.php` 4, `order_form.php` 4, `remove_recipient.php` 4, `reserve_calendar_event.php` 3, `print_packing_slip.php` 3, `approve_affiliate.php` 5 calls with values. `(string)` where the SQL quoted, also for database ids written unescaped into quotes (same text).
- Clauses with values carry them beside the SQL: the address book update's column list (`$sql_address_book_set_values`) and address type (`$sql_address_type_values`), the confirmation screen's `$where` (form id or reference code, `$where_values`), the reservation's calendar OR list (`$where_calendars_values`).
- `remove_recipient.php` keeps its bare `user = $user_id` inline in the `db_q()` template: a database id holds no `?`, and the text stays the same even when the user row is missing.
- `get_catalog_detail.php` runs its image lookups from `$item_images`; the guard test follows that variable as it follows `$query`.
- Raw stay the queries without values (the `next_order_number` lock of `includes/local_sale.php`, whose other reads use `db_value()` / `db()`) and the submitted form count of `get_form_list_view.php`, completed by `$sql_where`, `$sql_search`, `$sql_viewer_filter` with escaped filter values.

## How was it checked?
- As for `db-q-converted-front.md`; the four clause queries were run old against new with the value lists read from the files. `tests/db_q_small_front_test.php` guards the files. `tests/panel_missing_param_test.php` reads the packing slip's `?? ''` default in its `db_q()` form.

Related: docs/memory/data/db-q-converted-files.md (conversion rules), docs/memory/data/db-q-converted-front.md
