---
name: currency-form-rules
description: What the currency create/edit screens refuse and how the currency list sorts
module: ecommerce
---

## Can the base currency be deleted or switched off?

No (#5699). Without a `currencies.base = 1` row `init.php` falls back to
`USD` / `$`, so every price and payment would switch currency silently.
`edit_currency.php` refuses both with `pg_currency_form_delete_error()` and
`pg_currency_form_base_error()`; the base currency's form shows the switch
on and disabled (a hidden field posts 1) and no Delete button. The base
moves by making another currency the base.

## Which codes are accepted?

Three Latin capitals (ISO 4217). `pg_currency_form_code()` trims and
upper-cases the post before the conflict check and the save;
`pg_currency_form_code_error()` refuses anything else. The column is
`varchar(3)` and the session is not in strict mode, so a longer code would
be cut silently (a second `USD`); `update_exchange_rates.php` matches
currencies by code.

## Which way does the exchange rate go?

Units of this currency per 1 base unit (USD on a TRY store: 0.02002). The
form says so under the box; the list shows the reverse under each rate. A
rate above 99999.99999 (`decimal(10,5)`) is refused, not cut.

## How does the currency list sort?

`pg_currency_list_sort()` re-sorts the rows in PHP by the sort levels: the
name by `pg_currency_label()`, the symbol by its decoded character, both
with `pg_geo_label_compare()`; the SQL order of stored values did not match
what the screen shows.
