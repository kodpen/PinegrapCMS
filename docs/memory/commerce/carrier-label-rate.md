---
name: carrier-label-rate
description: Label driver rate() operation (#8406): quotes shape and checks, info services, Geliver's GET /priceList quotes and service codes
module: carrier_labels
---

## What is rate()?
- An optional driver operation `pg_label_<key>_rate($shipment, $account)` (2 arguments in `pg_label_operations()`): what the services would charge for the shipment's parcel. Called through `pg_label_call()` like every operation. Step R1 of plan `shipping-checkout-rates`; checkout does not charge it yet (R3).
- The answer's `quotes`: list of service, carrier, amount, currency.

## How is a quote checked?
- `pg_label_answer()` runs each through `pg_label_quote_normalize()`: service `pg_label_service_valid()` (1-60 printable ASCII, no space, so `label:<driver>:<service>` fits a 100-character cache row), amount an int of 0 or more (minor units, VAT inside), currency 3 letters (upper-cased), carrier `pg_label_carrier_key()` ('' when the store does not know it). A quote failing any is dropped, never repaired.
- rate() with no quote left is a plain failure, never `unclear`: asking for a price changes nothing at the service.

## What is info()'s services?
- Optional `_info()['services']`: the service's own codes, code => name, kept by `pg_label_info_normalize()` only with a valid code and a name. For the method screen's service picker (R2).

## How does Geliver quote?
- `GET /priceList?paramType=parcel&length&width&height&weight` (cm, kg; numbers as `pg_label_geliver_number()`): national, by parcel only, no address, test mode not sent. `priceList` sits beside `data` in the answer, not in it.
- `pg_label_geliver_rate_quotes()`: `totalAmount` (VAT and taxes in) to minor units with `round()`; TL is TRY; one quote per `providerServiceCode` (a service listed twice, e.g. the account's own `isProviderAccountOffer` contract and Geliver's: the lower); cheapest first; an offer without a code or a total of 0 or more is left out.
- `pg_label_geliver_services()`: the reference's provider service codes plus SENDEO_STANDART (seen in the price list), without the test service GELIVER_STANDART.
- A refused, unreached or empty list is a plain failure.

## How is it tested?
- `tests/carrier_labels_test.php` (quote checks, empty rate) and `tests/carrier_label_geliver_test.php` with `tests/fixtures/geliver/price_list.json`, shaped like the owner's test key answer of 2026-11-09 (8 offers for 1 desi, YURTICI_STANDART twice).
