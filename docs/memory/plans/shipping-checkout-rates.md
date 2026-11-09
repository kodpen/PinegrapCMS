---
name: shipping-checkout-rates
description: Plan (owner 2026-11-09): a shipping method linked to a carrier label service, its live price at checkout (Geliver, then Sendcloud and Shippo), its label made once the order is paid
---

## Why?
- On dev (2026-11-09) a store with a Geliver key charged the hand-entered method fee and sent nothing to Geliver until Create Label was pressed. Label drivers make labels only; the one live checkout rate is the legacy UPS/USPS one (`get_shipping_realtime_rate()` in `shipping.php`, `shipping_methods.service` ENUM).

## What did the owner decide?
- 2026-11-09 (`workflow/decisions-made-product-39.md`, `workflow/decisions-made-schema-13.md`): the schema below; the label made automatically once the order is paid; no price means the method is not offered; desi tariffs for carriers without a price call are a separate plan, later.

## What is decided technically?
- Link: `shipping_methods.label_driver` VARCHAR(32) and `label_service` VARCHAR(64) (`''`: the cheapest quote, the account's service on the label). `realtime_rate` = 1 charges the quote; a method with `label_driver` takes it from the driver, never from the legacy `service`.
- Quote: optional driver operation `rate($shipment, $account)` answering `quotes`: service, carrier, amount (minor units, VAT inside: the checkout's shipping holds tax, `pg_order_charges_taxed()`), currency. Geliver `GET /priceList` (national, by parcel; `totalAmount`; a service listed twice: the lower); Sendcloud `POST /shipping-options` quotes; Shippo the rates of `POST /shipments/`. Kargonomi (prices only on a draft), Basit Kargo and Aras get none.
- Parcel: the label's own (one parcel in kg and cm, as `pg_shipment_label_shipment()` builds it) over the lines that do not ship free. The fee stays quote + base rate + zone rate + weight and item rates + extra cost.
- Cache: `shipping_rates` with `service` VARCHAR(100): rows `label:<driver>:<service>`, zip `''` for a national list, else `<CC>-<zip>`; fresh for a day; when a call fails a row up to 7 days old is used; the purge keeps 7 days; one failed call per request.
- Currency: the base currency (Geliver's TL is TRY), else converted at the stored exchange rate, else no price.
- Create Label (single and bulk): the method's `label_driver` and `label_service` while that driver is ready, else today's pick.
- Automatic label: a switch per driver (`connect_apps` options `auto_label`, default off, `auto_label_since`) run by the general job, never in the checkout request. It takes orders not cancelled with `paid_at` > 0, paid after the switch and within 3 days, whose address has no label row and no tracking number; 10 a run. Each address is tried once: a failure writes a `shipment_labels` row, `state` `failed`, reference `failed-<ship_to_id>`, no file, plus the Site Log line, and Create Label stays on the order.

## Which files hold the rest?
- `shipping-checkout-rates-steps.md`: steps R1-R5, where to start, what is open.
