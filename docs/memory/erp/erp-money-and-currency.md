---
name: erp-money-and-currency
description: ERP money storage (integer minor units), the single rounding point in money.php, tax-inclusive splits, home-currency rule, *_base columns, opt-in FX switch, rate history and the fx_diff ledger line.
---

## How is money stored and rounded in the ERP?
- Integer minor units (kuruş), `BIGINT`, same as the rest of the codebase; no `bcmath`. Rates `DECIMAL(6,3)` / `DECIMAL(15,6)`, quantities `DECIMAL(15,4)`.
- `includes/erp/money.php` is the single multiply/round point; do not open a second one. `erp_apply_rate()` is the only amount × rate, `erp_line_total()` the only amount × quantity, `erp_allocate()` splits one amount over lines (largest remainders, the parts always add up). Rounding is `round()` (half away from zero, as the order side does), never an `(int)` cut.
- A tax-inclusive amount is split with `erp_split_gross($gross, $rate)` (net + tax = gross exactly, rate × net within a kuruş); never `/ (1 + rate)`. An order's VAT is taken per rate on the sum of the rate's lines (`erp_rate_vat()`, #1168), and a rate's gross total is split with `erp_rate_amount()` (#1211): erp-discounted-order-vat, erp-order-charges-vat.

## How many places does a quantity keep?
- Four, as stored. `erp_manual_lines_build()` takes it through `erp_quantity_round()` before pricing (#1230). Before, 1.3333333333 x 1,500.00 showed 2,400.00 on the draft, but the issued invoice was 2,399.94 because issue rebuilds the lines from the stored 1.3333. The editor's `quantityRound()` does the same.
- `erp_decimal_units($value, $places)` rounds on the digits `sprintf('%.10F')` writes (JS `toFixed(10)` writes the same ones), half away from zero. It never rounds a binary product such as `1.15 * 10000`.
- Incoming e-invoice line: the price is checked against the stored quantity. A longer quantity whose price does not add up folds into one unit, with the supplier's figures in the text.

## What is the home currency?
- The store's main currency, not `'TRY'`: the ERP follows `BASE_CURRENCY_CODE` (`erp_base_currency()`, `includes/erp/fx.php`). Pinegrap installs worldwide; no country-specific rate source or tax rule enters the core.
- Converted columns are `*_base` (`amount_base`, `grand_total_base`; renamed from `*_try` before 2026.4.4 shipped, migration 4.49).
- Convert with `erp_to_base($kurus, $rate)`; rate = home currency per 1 unit of document currency.
- Display with `erp_money_out_currency($kurus, $code)` (symbol from the `currencies` table); the visitor currency rate is NOT applied. `erp_money_out()` is its home-currency wrapper. Separators and other formats: erp-number-input-and-display.

## How is foreign currency switched on?
- Opt-in: `config.erp_fx_enabled` / `ERP_FX_ENABLED`. Off: forms do not ask for a currency, the home currency is used silently, no FX difference is written. Every FX branch checks `erp_fx_enabled()`.
- On: allowed codes = `ERP_FX_CURRENCIES` ∩ `currencies` − home (`erp_fx_currencies()`).
- Rate history: `currency_rates (rate_date, base_code, currency_code) UNIQUE`, written by `update_exchange_rates.php` (fetch in `includes/fn/currency_rates.php`: Frankfurter then HexaRate, TLS verification on). `pg_currency_rate($code, $date)` returns that day or the last earlier day.
- An invoice from an order is always home currency; a foreign-currency invoice is entered by hand (`add_erp_manual_invoice.php`, see erp-manual-invoice-draft-issue).

## How is the exchange difference posted?
- Only by `erp_fx_post_difference()`, inside the transaction that makes a foreign invoice `paid`: a receipt (`erp_post_receipt()`, `cash.php`), a later allocation (`erp_settlement_allocate()`, `settlement.php`) or a return (`erp_invoice_return()`). Only while `erp_fx_auto_diff()` (FX on and `config.erp_fx_auto_diff`, default 1).
- One `kind='fx_diff'` row (`doc_type='fx_diff'`, `doc_id` = invoice as idempotency key). Amount = `Σ settlement.amount_base − (grand_total_base − Σ return.grand_total_base)`.
- A return closes the invoice when what was collected now covers the rest (1,000 EUR invoiced at 30, 600 collected at 35, 400 returned: 21,000 − 18,000 = 3,000 difference). Until #1071 the return path posted nothing, and since no receipt closes a paid invoice the account kept the gap in the base currency for good.
- Reversal when a foreign invoice leaves `paid` (`erp_settlement_after_reopen()`): a receipt cancelled, a settlement removed, or a return cancelled (#1071; before, the un-reversed row made the next closing post nothing). Details: erp-receipt-corrections-and-gift-card.

Related: docs/memory/erp/erp-manual-invoice-draft-issue.md, docs/memory/erp/erp-number-input-and-display.md
