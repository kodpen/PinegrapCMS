---
name: shipping-checkout-rates-steps
description: Steps R1-R5 of plans/shipping-checkout-rates.md: Geliver price list, method link, checkout live rate, label on payment, Sendcloud and Shippo quotes; claim as [plan:shipping-checkout-rates] R<n>
---

## Which steps are there?
- S under 400 product lines, M about 1,000. Each step adds its tests and corrects its topic files (`commerce/carrier-label-drivers.md`, `carrier-label-<key>.md`, `shipment-labels.md`).

- R1 (S) Driver quote, Geliver price list. Done (#8406).
  - `rate` in `pg_label_operations()` (2 arguments); `pg_label_answer()` keeps `quotes`, each checked (service text, whole amount of 0 or more, currency); `pg_label_geliver_rate()` on `GET /priceList?paramType=parcel&length&width&height&weight`; Geliver's service codes in `_info()['services']` (code => name). Files: `includes/fn/carrier_labels.php`, `includes/shipping/geliver.php`, tests, a fixture shaped like the 2026-11-09 answer. No schema.

- R2 (M) Schema and method link. Done (#8403).
  - A sub-step of `2026.11.0.php`: `install_add_column()` for `shipping_methods.label_driver` and `label_service`, `install_modify_column()` for `shipping_rates.service` VARCHAR(100) NOT NULL DEFAULT ''. The method screen's "Kargo servisi" picker (drivers on and ready; a driver's services, else a text box; the cheapest), the live-rate switch's words, `pg_shipping_method_save()`, the external API's shipping method fields, `carrier` set from the service when the driver maps it. Create Label and bulk create follow the link (`includes/fn/shipment_labels.php`). The `[SCHEME]` item goes in the PR body.

- R3 (M) Live rate at checkout. After R1 and R2.
  - A first branch in `get_shipping_realtime_rate()`; the six callers select the two columns; the parcel function shared with `pg_shipment_label_shipment()`; the cache, the stale fallback and the 7-day purge; the method not offered without a price; the currency rule. Tests for the pure parts (quote pick, cache key, parcel). The plan's `[NEW]` line goes in this PR body; `commerce/carrier-label-geliver.md`'s checkout-fee line is corrected.

- R4 (M) Automatic label on payment. After R2; not beside R3 (both edit `includes/fn/shipment_labels.php`).
  - The switch on the Shipping card for every driver that can create; `pg_shipment_label_auto_run()` in `job.php`; `failed` in `pg_shipment_label_states()`, its row on the order screen ("Otomatik etiket oluşturulamadı") and in the API's labels; the run's limits.

- R5 (S) Sendcloud and Shippo quotes. Done (#8420).
  - `pg_label_sendcloud_rate()` (shipping options with quotes, create's route checks), `pg_label_shippo_rate()` (the rates of `POST /shipments/`, an 8-second timeout); the address-priced cache key.

## Where to start?
- R1 and R2, in parallel.

## What is open?
- Whether Geliver's price list holds an account's own carrier contract prices (its `isProviderAccountOffer` offers) or only Geliver's. A label bought later may cost a little more or less than the quote; the store bears the difference.
- The price list takes no city: Geliver's domestic prices go by parcel only (2026-11-09, the owner's test key: 8 offers for 1 desi, about 90 ms).
- Left out (owner): desi tariff tables for carriers without a price call, a separate plan later.
