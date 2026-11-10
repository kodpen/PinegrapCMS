---
name: shipping-label-checkout-rate
description: Checkout live rate of a method linked to a label service (R3): get_shipping_realtime_rate() branch, parcel, cache rows, stale fallback, purge, currency, no price = not offered
module: shipping_label_rates
---

## What does checkout charge for a linked method?
- `get_shipping_realtime_rate()` (`shipping.php`): a method with `realtime_rate` on and `label_driver` set goes to `pg_shipping_label_rate()` (`includes/fn/shipping_label_rates.php`) first, never to the legacy UPS/USPS `service`. A link with the switch off charges 0, like before.
- The amount: the quote of `label_service` (letter case aside), else the cheapest quote; minor units of the base currency, VAT inside. The rest of the fee is unchanged: quote + base rate + zone rate + weight and item rates + extra cost.
- No price (driver not ready or without `rate`, no quote for the service, call failed and no stale row) → `false` → the method is not offered (lists skip it; a picked method marks the recipient incomplete).
- Every line ships free → 0, no call.

## Which callers pass the link?
- All six: `update_shipping_cost_for_ship_to()` and `get_shipping_methods()` in `shipping.php`, `shipping_method.php`, `shopping_cart.php`, `get_shipping_method.php` (two). Each selects the columns through `pg_shipping_label_rate_select($table, $prefix)`, which reads `'' AS label_driver` on a site that has not run step 11.0.177 yet (files land first), and passes `label_driver`, `label_service`, `country` (and `city` where known; with `ship_to_id` the missing ones are read from `ship_tos`).

## What parcel is asked for?
- `pg_shipment_label_parcel()` (`shipment_labels.php`), the one the label uses (`pg_shipment_label_shipment()` calls it): kg and cm, weights summed by quantity, the largest size of a line, 1 kg / 10 cm when missing. Lines: those not shipping free (`pg_shipping_label_rate_lines()`).
- The rate() shipment: `recipient` (country, zip, state, city), `parcel`, `service`, `currency` (base).

## How is it cached?
- `shipping_rates`, one row per service of the answer: `service` `label:<driver>:<service>`, `zip_code` the zone (`pg_label_rate_zone()` when present, i.e. '' for a national list such as Geliver's, else `<CC>-<zip>`; '' while that function is not there), the parcel in `weight`/`length`/`width`/`height`, `rate` in base minor units. A new answer replaces the driver's rows for that zone and parcel.
- Fresh for a day (`PG_SHIPPING_LABEL_RATE_FRESH`); a fresh set that lacks the service means no price (no new call).
- A failed call (or one with no quote convertible to the base) is logged and remembered for the request (`pg_shipping_label_rate_failed()`): the driver is not asked again in that request, and a row up to 7 days old (`PG_SHIPPING_LABEL_RATE_STALE`) is charged.
- Purge: `pg_shipping_rates_purge()` drops rows older than 7 days, 1 call in 100 (both branches). The legacy UPS/USPS rows are still read only when under a day old.

## Which currency?
- `pg_shipping_label_rate_in_base()`: a quote in the base currency as it is; another divided by `currencies.exchange_rate` (units per one base unit) and rounded; one with no rate is dropped. Geliver's TL is TRY (driver).

## How is it tested?
- `tests/shipping_label_rates_test.php`: lines, parcel (and the label's same parcel), cache key length, base-currency amounts, pick. The database parts (cache read/write, purge, the call) were not run on a dev DB in the PR session.
