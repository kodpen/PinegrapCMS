---
name: api-store-shipping-method-writes
description: Shipping method writes (store:write): write path shared with the screens, typed fields to columns, base rate tiers, days and times, zones, excluded dates, events
module: shipping_methods
---

## Where is a shipping method written?
- `includes/fn/shipping_methods.php`: `pg_shipping_method_save()` (insert or update, then zones and excluded transit dates replaced by `pg_shipping_method_save_links()`), `pg_shipping_method_delete()` (also its zones, excluded dates, offer actions, cut-offs and ship date adjustments, as the screen always did). `add_shipping_method.php`, `edit_shipping_method.php` and the API call them.
- The screens still build their columns from the formatted form with `pg_shipping_method_form_columns()` (site date order, money mask); the API builds them from typed fields with `api_store_shipping_method_columns()` (`includes/api/resources/store.php`). Both hand the same column set over.
- `pg_shipping_method_services()` is the `service` ENUM; a test holds it equal to the column and to the screen's picker template.

## How do the API fields map?
- The body mirrors GET's `StoreShippingMethod`: `enabled` is `status`, `available_from`/`available_until` are `start_time`/`end_time` (null is 1000-01-01 / 9999-12-31, "always"; read on the site's clock; until before from is 422), `offered_on` the `available_on_<day>` flags with their cut-off, `handling_on`/`transit_on` the `handle_`/`ship_` flags, `base_rates` the tiers.
- `base_rates`: one tier `from_subtotal` 0 is `base_rate`; up to three more set `variable_base_rate` and `base_rate_2…4` in subtotal order. Amounts are whole minor units; a decimal is refused.
- `realtime_rate` is kept off without a service or with a FedEx service, as on the screen.
- An update starts from the stored row: only fields sent change; a sent list replaces the stored one. A create starts from `api_store_shipping_method_new_row()`: enabled, every day, weekdays for handling and transit, no window, no zone.
- `zone_ids` must all name a zone (422); `excluded_transit_dates` are YYYY-MM-DD. GET now carries `excluded_transit_dates` too (one batch query per page).

## Which events?
- `shipping_method.created|updated|deleted` with `id` and `name`, from the shared functions.

## Why?
- Step X1b, after the zones (`api-store-zone-writes.md`): an outside system can now set up shipping in full.
