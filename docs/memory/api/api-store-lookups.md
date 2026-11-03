---
name: api-store-lookups
description: Store lookup lists (arrival dates, ship date adjustments, verified addresses, referral sources): the write path the screens and the API share, the referral source routes, refusals, cut-off rule
module: store_lookups
---

## Where is a row written?
- `includes/fn/store_lookups.php`: `pg_store_arrival_date_save()`, `_cutoffs_save()`, `_delete()`, `pg_store_ship_date_adjustment_save()`/`_delete()`, `pg_store_verified_address_save()`/`_delete()`, `pg_store_referral_source_others()`/`_save()`/`_delete()`. The eight add/edit screens call them and keep no SQL for these rows; the API handlers call them too. They return 0/false on a failed query (screens `output_error('Query failed.')`, API 500).
- An arrival date saved as default takes `default_selected` off every other one; a delete takes its `shipping_cutoffs`.
- The screens' checks stay in `includes/forms/*_form.php`; the ship date adjustment's two database checks are `pg_ship_date_adjustment_form_taken()` and `pg_ship_date_adjustment_form_method_exists()`, so a caller without a liveform can run them.

## Which shipping cut-off is kept?
- `pg_arrival_date_form_cutoff()`: a shipping method no earlier row names and a valid date and time between the start (00:00:00) and the end (23:59:59). The screen leaves out a row that fails it and shows its notice; `pg_arrival_date_form_save_cutoffs()` then replaces the stored rows.

## Which routes are there?
- `/store/referral-sources`: list (one page, in the contact screen's order) and GET one (`orders:read`), create (201), update (POST, PATCH also accepted), DELETE and its `POST …/delete` twin (`store:write`); ids `store.referral_sources.*`, handlers in `includes/api/resources/store_lookups.php`. No events.
- Refusals: `pg_referral_source_form_error()` without the other sources first (blank name or code, sort order outside 0-999: 422), then with them (a name or code another source has, any case: 409 `referral_source_taken`). An update lays the sent fields over the stored row (`api_store_referral_source_values()`, pure).
- Arrival dates, ship date adjustments and verified addresses get their routes in the plan's later steps (`plans/external-api-coverage.md`).

## Tested?
- `tests/api_store_lookups_test.php`: routes, scopes, field limits, the partial update, the cut-off rule, check-stop-write order, the screens calling the shared functions with no SQL of their own, placeholders. Not run against a database.
