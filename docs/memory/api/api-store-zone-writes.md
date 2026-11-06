---
name: api-store-zone-writes
description: Shipping zone and tax zone writes (store:write): shared write functions the zone screens call too, partial updates, country and state input, zone.* and tax_zone.* events
module: store_zones
---

## Where is a zone written?
- `includes/fn/store_zones.php`: `pg_store_zone_save()`, `pg_store_zone_delete()`, `pg_store_tax_zone_save()`, `pg_store_tax_zone_delete()`. `add_zone.php`, `edit_zone.php`, `add_tax_zone.php`, `edit_tax_zone.php` and the API handlers all call them, so both write the same rows and announce the same event. They return 0/false on a failed query; the screens answer `output_error('Query failed.')`, the API 500.
- A save replaces the zone's countries and states (`pg_store_zone_save_links()`); ids are cleaned by `pg_store_zone_link_ids()` (distinct positive integers).
- A shipping zone delete also drops its rows in `shipping_methods_zones_xref` and `products_zones_xref`, as the screen always did.

## What does the API take?
- Routes in `api_schema()` (`store.zones.*`, `store.tax_zones.*`): GET one, create (201), update (POST, PATCH also accepted), DELETE and its `POST …/delete` twin; handlers in `includes/api/resources/store.php`.
- Reads stay under `orders:read`; writes need `store:write` (group `store`, read side empty like `inventory`), in the commerce ceiling of `api_owner_scopes()` beside `orders:write`.
- Countries come as ISO `country_codes`, states as `{country_code, code}` objects, the same shape GET answers; an unknown one is 422 naming the field. An update changes only the fields sent; a sent list replaces the stored one, `[]` empties it.
- A name is at most 50 characters (varchar(50)); rates are minor units (`money`); a tax rate 0–100 with three decimals (decimal(6,3)), required on create.
- `api_dry_run_stop()` sits in each handler after every check: the handler checks, stops, then commits (`api_store_zone_write_prepare()` / `_commit()`), because `check_api_schema.php` looks for the stop in the handler itself.

## Which events?
- `zone.created|updated|deleted`, `tax_zone.created|updated|deleted` with `id` and `name`, from the shared functions: a change on the zone screens is announced too.

## Why?
- Gap list #5239 (`api-panel-coverage.md`): an outside system that syncs shipping and tax needs to write zones before shipping methods can name them (X1b).
