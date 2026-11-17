---
name: erp-money-overflow
description: ERP amounts too large for an integer: the typed figure, the exchange rate bound and the home-currency total bound, checked where a document is saved or issued
---

## Which bounds keep ERP money inside an integer?
- `ERP_MAX_LINE_AMOUNT` (1e15 kurus) and `ERP_MAX_QUANTITY`: a line, checked by `erp_line_amount_fits()`; 200 lines with both taxes at 100% stay near 6e17 (erp-line-total-and-rate-rounding).
- `ERP_MAX_EXCHANGE_RATE` (999,999,999.999999, what the `DECIMAL(15,6)` rate columns store): checked by `erp_exchange_rate_refusal()` in `erp_manual_header_build()`, so a manual invoice, a purchase bill and a quote refuse a larger rate on the Exchange rate box.
- `ERP_MAX_BASE_AMOUNT` (1e18 kurus): `erp_base_amount_fits($grand_total, $rate)` before every `erp_to_base()` of a document total in `invoice_manual.php` (draft save and issue) and `quotes.php` (quote save). Refused with "The total is too large in the home currency."

## Why refuse at the entry point instead of clamping?
- `erp_to_base()` is `(int) round(kurus × rate)`. Past PHP_INT_MAX the cast gave a random number on PHP 8.3 and logs "The float … is not representable as an int" from PHP 8.5 (census part 3, EL5). A clamp inside the arithmetic would store a wrong total silently; a refusal shows the operator which box to fix. Normal amounts convert exactly as before.
- The issue path checks again (with a rollback, before a number is taken): a draft saved before the bound, or the recorded rate of the issue date, may still be too large.

## What does erp_kurus() return for a huge typed figure?
- More than 16 whole digits: the float it is (`(float) $whole * 100`), never `(int)` of the digit string, which saturated at PHP_INT_MAX and then overflowed on `* 100`. The caller's cap refuses it: `erp_manual_lines_build()` holds the price within ten times the line cap before its cast and refuses it on the unit price box. Up to 16 whole digits the result is the same int as before.
- Tests: `tests/erp_money_overflow_test.php`.
