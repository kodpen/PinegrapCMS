---
name: currency-exchange-rate-box
description: Exchange rate box on add/edit currency: five decimals like currencies.exchange_rate and the rate updater, filled and stored with five; 0,0285 no longer saved as 0,03 (#1335)
---

## How many decimals does the exchange rate box take?
- Five. `currencies.exchange_rate` is `decimal(10,5)` and `update_exchange_rates.php` writes `number_format($rate, 5, '.', '')`, so the panel box matches both.
- `add_currency.php` and `edit_currency.php` draw it with `pg_money_mask_attributes(null, 5)` (the money mask with five decimals always shown, `money-field-input-mask.md`), `maxlength` 12 (`99.999,99999`, the column maximum with grouping) and `inputmode="decimal"`, so a phone keyboard offers the decimal separator a rate below one needs.
- Every other money box keeps the default two decimals: `$decimals` defaults to 2 in `pg_money_mask_attributes()`, `pg_money_mask_html()` and `pg_format_input_amount()`.

## How is it filled and stored?
- Filled with `pg_format_input_amount($row['exchange_rate'], 5)`: the panel's decimal separator, no grouping (`0,02853` in Turkish).
- Read with `pg_normalize_amount()`; a numeric result is stored as `number_format((float) $rate, 5, '.', '')`, so a small rate is written `0.00006`, not the float cast `6.0E-5`. A base currency is still forced to 1.
- The mask always posts all five decimals, so the three-digits-after-a-single-separator rule of `pg_parse_amount()` never decides a posted rate.

## What was the symptom?
- #1335: the box had the two-decimal money mask and was filled with two decimals, so a rate the updater stored as 0.02853 opened as 0,03 and any save of the currency (even of its name) wrote 0.03 back. Rates already saved that way stay rounded until the updater or an admin rewrites them.

## What was checked?
- `tests/money_mask_test.php`: five digits in the mask attributes and tag form, two stay the default, the five-decimal fill, and `0,02853`, `16.000,00000`, `16,000.12345` read back.
- Not checked in a browser: Inputmask 5.0.7 with `digits` 5 on a Turkish panel (typing `1.234,5` should pad to `1.234,50000`). Live installs upgraded from old versions were not checked for a different column scale; MySQL would round to that scale silently, as before.
