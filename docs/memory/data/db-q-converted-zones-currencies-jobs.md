---
name: db-q-converted-zones-currencies-jobs
description: Tax zone, state, currency, add, order edit and payment job screens converted to db_q(): PR, bare ids bound as int, what stayed raw, guard test.
---

## Which files are converted, and how?
- `edit_tax_zone.php` 6, `edit_state.php` 6, `edit_currency.php` 5, `update_currency.php` 3, `add_zone.php` 3, `add_shipping_method.php` 3, `add_arrival_date.php` 3, `add_product_group.php` 2, `edit_orders.php` 4, `view_orders_for_contact.php` 3, `membership_job.php` 6, `recurring_payment_job.php` 4 calls with values (PR #PRNUM).
- `(string)` where the SQL quoted, also where a value was written unescaped into quotes (row and contact ids of the jobs, dates, `$user['id']`, the `MEMBERSHIP_*` page and group ids, the session order id of `update_currency.php`: now escaped). Rates and prices stay the text they were (`pg_normalize_amount()` strings).
- `(int)` where the SQL wrote a bare number: the new zone and shipping method ids (`mysqli_insert_id()`), the new arrival date id, the `user` column of the zone and shipping method INSERTs.
- `view_orders_for_contact.php` builds `$where` with one `?`; both its queries bind the contact id.
- Both jobs load `functions.php` through `init.php`, so `db_q()` exists there.

## What stayed raw?
- The calls without values; `view_recurring_commission_profiles.php` is unchanged (its list query has only a whitelisted ORDER BY and an integer LIMIT).
- `tests/db_q_zones_currencies_jobs_test.php` guards them: no escaped value in a `db_q()` SQL, no value in a raw query, one value per `?` (the `$where` clause counted), and the bound text of the bare ids and a rate.

Related: docs/memory/data/db-q-converted-files.md (conversion rules), docs/memory/data/db-q-placeholder-helper.md
