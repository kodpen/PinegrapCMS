---
name: db-q-converted-shipping-import
description: Shipping, zone, arrival date, commission, commerce settings and product import files converted to db_q() (PR #2314): how values are passed, what stayed raw and why, the guard test.
---

## Which files are converted, and what stayed raw?
- PR #2314: `edit_shipping_method.php` 11, `edit_zone.php` 11, `shipping_method.php` 7, `edit_arrival_date.php` 7, `shipping.php` 6, `import_products.php` 6, `view_commissions.php` 4, `includes/settings/commerce.save.php` 3 calls with values.
- `(string)` where the old SQL quoted a value, also for values written unescaped into quotes (`"'$shipping_cost'"`, the session order id, `(int) $order['id']`), now escaped once: an interpolated `"$x"` is the same text as `(string) $x`, a rounded float cost included. `(int)` for the bare shipping method id of the first zone check in `get_shipping_methods()` (`shipping.php`), an id read by `db_values()`.
- Prices are integer minor units already computed before the query (`round()`); the conversion passes them as they were and changes no arithmetic.
- `view_commissions.php` passes the `mktime()` bounds `(string)`: a failed `mktime()` (`false`) still writes `''`.
- The card number re-encryption of `commerce.save.php` binds the encrypted text; the `next_order_number` INSERT between `LOCK TABLES` and `UNLOCK TABLES` is still a single query there.
- Raw stay the queries without values, the shipping method list completed by `$sql_zones` with the bare `$address_type` / `$day_of_week` column names (`shipping.php`), the commission count and list with `$sql_status` / `$order_by` / `LIMIT`, and the product import UPDATE / INSERT built from `$update_values` / `$insert_values`.

## What guards it?
- `tests/db_q_shipping_import_test.php`: no `escape()` / `e()` in a `db_q()` SQL, no value written into a remaining raw query, one value per `?`, no quoted `?`, and bind examples (bare zone check id, rounded cost, failed `mktime()`).

Related: docs/memory/data/db-q-converted-files.md (conversion rules), docs/memory/data/db-q-converted-commerce.md
