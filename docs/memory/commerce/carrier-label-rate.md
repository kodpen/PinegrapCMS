---
name: carrier-label-rate
description: Label driver rate() operation (#8406, Sendcloud and Shippo #8420): quotes shape and checks, info services and rates, the rate zone, Geliver, Sendcloud and Shippo quotes
module: carrier_labels
---

## What is rate()?
- An optional driver operation `pg_label_<key>_rate($shipment, $account)` (2 arguments in `pg_label_operations()`): what the services would charge for the shipment's parcel. Called through `pg_label_call()` like every operation. Step R1 of plan `shipping-checkout-rates`; checkout charges it for a linked method (`shipping-label-checkout-rate.md`).
- The answer's `quotes`: list of service, carrier, amount, currency.

## How is a quote checked?
- `pg_label_answer()` runs each through `pg_label_quote_normalize()`: service `pg_label_service_valid()` (1-60 printable ASCII, no space, so `label:<driver>:<service>` fits a 100-character cache row), amount an int of 0 or more (minor units, VAT inside), currency 3 letters (upper-cased), carrier `pg_label_carrier_key()` ('' when the store does not know it). A quote failing any is dropped, never repaired.
- rate() with no quote left is a plain failure, never `unclear`: asking for a price changes nothing at the service.

## What else does info() declare for rates?
- Optional `services`: the service's own codes, code => name, kept by `pg_label_info_normalize()` only with a valid code and a name. For the method screen's service picker (R2).
- `rates`: `national` (prices by parcel alone: Geliver) or `address` (the default). `pg_label_rate_zone($info, $recipient)` is the cache row's zip: '' for national, else `<CC>-<ZIP>` upper-cased without spaces, at most 40 characters (`shipping_rates.zip_code` is 50).

## How does Geliver quote?
- `GET /priceList?paramType=parcel&length&width&height&weight` (cm, kg; numbers as `pg_label_geliver_number()`): national, by parcel only, no address, test mode not sent. `priceList` sits beside `data` in the answer, not in it.
- `pg_label_geliver_rate_quotes()`: `totalAmount` (VAT and taxes in) to minor units with `round()`; TL is TRY; one quote per `providerServiceCode` (a service listed twice, e.g. the account's own `isProviderAccountOffer` contract and Geliver's: the lower); cheapest first; an offer without a code or a total of 0 or more is left out.
- `pg_label_geliver_services()`: the reference's provider service codes plus SENDEO_STANDART (seen in the price list), without the test service GELIVER_STANDART.
- A refused, unreached or empty list is a plain failure.

## How do Sendcloud and Shippo quote?
- Sendcloud: the checks of create() first (recipient country, sender address, `pg_label_sendcloud_route_error()`; a customs route only with the customs block filled, then the account's incoterm), then `POST /shipping-options` with `calculate_quotes`. Every option `pg_label_sendcloud_option_priced()` allows (the rules of `_option_pick()`: no letter, no service point, no export documents unless declared) is a quote, `price.total` in its currency. Test mode is quoted too: pricing costs nothing.
- Shippo: `POST /shipments/` without the order reference, 8-second timeout (a customer waits), never read again; `pg_label_shippo_rate_quotes()`: one per `servicelevel.token` (the lower), `amount_local`/`currency_local` when given, else `amount`/`currency`.
- Unverified: whether Sendcloud's and Shippo's totals include VAT (the quote is taken as charged).

## How is it tested?
- `tests/carrier_labels_test.php` (quote checks, empty rate, rate zone), the Sendcloud and Shippo driver tests with their existing fixtures, and `tests/carrier_label_geliver_test.php` with `tests/fixtures/geliver/price_list.json`, shaped like the owner's test key answer of 2026-11-09 (8 offers for 1 desi, YURTICI_STANDART twice).
