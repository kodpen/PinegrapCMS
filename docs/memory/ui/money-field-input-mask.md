---
name: money-field-input-mask
description: Panel money boxes with the currency input mask: panel-language separators (pg_money_mask_attributes()), filled with pg_format_input_amount(), read with pg_normalize_amount() (#728, #986)
---

## How is a money box drawn?
- With `pg_money_mask_attributes()` (array, for `$liveform->output_field()`) or `pg_money_mask_html()` (attribute string for a raw tag), `includes/fn/ecommerce.php`: Inputmask alias `currency`, two decimals always shown, `radixPoint` and `groupSeparator` from `pg_number_separators()`. A Turkish panel reads and shows `1.234,56`, an English one `1,234.56`. Never write the mask attributes by hand.
- `liveform::output_field()` forwards every `data-inputmask-*` key it is given (empty values too: an empty `groupSeparator` is no grouping), so the array form works on liveform screens. Before #1263 it wrote only the alias and a truthy placeholder; the gift card and currency boxes kept the default `.` decimal while filled `1234,56`, and a Turkish balance or exchange rate was saved 100 times larger.
- Screens: gift card add/edit, product screen (price, compare-at price, extra shipping cost), zone add/edit, shipping method add/edit (rates and the variable base rates), currency add/edit (exchange rate, still two decimals).
- `backend.src.js` (top, before the binding masks on document ready) extends the `currency` alias: Inputmask's numeric alias turns the separator that is not the decimal point into the decimal point as it is typed (`opts.substitutes`), so a typed thousands separator became the decimal point (`12.500` on a Turkish panel was 12,50). The extension drops the substitute for the group separator; the numpad decimal key still types the decimal point.

## How is a masked field filled?
- With `pg_format_input_amount($minor_units / 100)`: the panel's decimal separator, no grouping (`1234,56` in Turkish). The mask splits a starting value on its own decimal separator and drops every other non-digit, so a machine `1234.56` on a Turkish panel would show 123.456,00. A value posted back after a validation error (`1.234,56`) is read the same way.
- `product_builder.js` copies the price into the variant matrix's number boxes through `matrixPrice()` (machine `1234.56`; a number box refuses `1.234,56`).

## How is it read back?
- `pg_normalize_amount()` (or `pg_pb_price_to_cents()` on the product screen) takes either way of typing, then `(int) round($value * 100)`. An empty value has to be refused first (`validate_required_field()`): `'' * 100` is a TypeError on PHP 8.

## What were the symptoms?
- #728 (PR #925): the gift card balance was filled as `pg_format_number()` text the mask read as "." decimal, so saving only the notes or the expiry date turned ₺50,00 into ₺5.000; an empty balance gave a 500 (now required).
- #986 (PR #1263): the mask read "." as the decimal point in every language, so on a Turkish panel `12.500` saved ₺12,50 and `1.234,56` ₺1,56 (gift cards, product prices, zone and shipping rates). The variable base rates stripped every comma (`1.234,56` → 1.23456). The product screen also put the extra shipping cost in its box in minor units (500 for ₺5,00), so every save multiplied it by 100.

## What was checked?
- `tests/money_mask_test.php`, `tests/liveform_test.php` (the forwarded options). A static page with the panel's jQuery, Inputmask 5.0.7 and binding: Turkish and English boxes fill `1234,56` / `1234.56` as `1.234,56` / `1,234.56`, a reposted `12.500,00` stays, typed `12.500` and `1.234,56` (text input) stay; without the extension the Turkish box carries the `.` → `,` substitute. Not checked on a running panel with real keyboard keypresses.
