---
name: db-q-converted-page-type-records
description: forms.php page type record write and duplicate_products.php disallowed zones on db_q(); why the rest of forms, price_history, view_orders and duplicate_products stays raw.
---

## What was converted?
- `includes/fn/forms.php`, `create_or_update_page_type_record()` (3 calls): the COUNT, the INSERT and the UPDATE of `<page type>_pages`. `$sql_collection` holds `?` with `$collection_values` beside it, the INSERT writes one `?` per property (`$placeholders`, `$insert_values`), `$sql_update` holds `column = ?` with `$update_values`. Every value is `(string)`, as the old SQL quoted all of them; an unset INSERT property stays `''`. The table name and the property keys (column names from the callers' code) stay in the SQL text.
- `duplicate_products.php`, the disallowed zones of a copy (1 call): `$disallowed_zones_sql_where` holds `id != ?` per allowed zone with `$disallowed_zones_values`; the text keeps its missing space before `AND`.

## What stays raw, and why?
- `includes/fn/forms.php` (27 raw left): queries without values (page type lookups, countries, currencies, referral sources, offer rules, contact groups, states, the log trim), the file lists completed by the constant `$sql_design`, the product group list `$sql_where` and the two product lists completed by `pg_catalog_bin_live_sql()` (integers and literals only).
- `includes/fn/price_history.php`: all SQL comes from the pure builders `pg_price_history_*_sql()` (tested on their own), the integer IN list or has no value.
- `view_orders.php`: every query left is completed by `$where`, `$sql_status`, `$sql_type`, `$order_by` (the filter screen's escaped LIKE clauses, shared by the list, the counts and the five exports) or has no value. Converting them means turning `$where` into a value list for all twelve queries at once.
- `duplicate_products.php`: the counts with `$live_products_sql` (no value), the list with `$where`, and the products INSERT completed by `pg_product_customs_copy_sql()` and `pg_product_copy_extra_sql()` (escaped `ai_fields` and `vat_exemption_code`, which may hold a `?`).

## What guards it?
- `tests/db_q_auth_contacts_designer_test.php` (forms.php: at least 27 `db_q()` calls, `$sql_collection` / `$sql_update` / `$placeholders` never built with an escaped value, the bound INSERT and UPDATE text) and `tests/db_q_order_screens_test.php` (duplicate_products.php: at least 10 calls, a `$…_values` list accepted beside a built clause, the bound zones text). `tests/db_q_submit_order_forms_test.php` no longer lets `$sql_collection` / `$sql_update` through.

Related: docs/memory/data/db-q-converted-files.md (conversion rules), docs/memory/data/db-q-converted-commerce.md, docs/memory/data/db-q-converted-access.md
