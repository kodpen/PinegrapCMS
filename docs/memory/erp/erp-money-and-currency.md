---
name: erp-money-and-currency
description: ERP money storage (integer minor units), home-currency rule, *_base columns, opt-in FX switch, rate history and the fx_diff ledger line.
---

## How is money stored in the ERP?
- Integer minor units (kuruş), `BIGINT`, same as the rest of the codebase; no `bcmath`.
- Rates `DECIMAL(6,3)` / `DECIMAL(15,6)`, quantities `DECIMAL(15,4)`.
- `includes/erp/money.php` is the single multiply/round point.

## What is the home currency?
- The store's main currency, not `'TRY'`: the ERP follows `BASE_CURRENCY_CODE` (`erp_base_currency()`, `includes/erp/fx.php`). Pinegrap installs worldwide; no country-specific rate source or tax rule enters the core.
- Converted columns are `*_base` (`amount_base`, `grand_total_base`; renamed from `*_try` before 2026.4.4 shipped, migration 4.49).
- Convert with `erp_to_base($kurus, $rate)`; rate = home currency per 1 unit of document currency.
- Display with `erp_money_out_currency($kurus, $code)` (symbol from the `currencies` table); the visitor currency rate is NOT applied. `erp_money_out()` is its home-currency wrapper.

## How is foreign currency switched on?
- Opt-in: `config.erp_fx_enabled` / `ERP_FX_ENABLED`. Off: forms do not ask for a currency, the home currency is used silently, no FX difference is written. Every FX branch checks `erp_fx_enabled()`.
- On: allowed codes = `ERP_FX_CURRENCIES` ∩ `currencies` − home (`erp_fx_currencies()`).
- Rate history: `currency_rates (rate_date, base_code, currency_code) UNIQUE`, written by `update_exchange_rates.php` (fetch in `includes/fn/currency_rates.php`: Frankfurter then HexaRate, TLS verification on). `pg_currency_rate($code, $date)` returns that day or the last earlier day.
- An invoice from an order is always home currency; a foreign-currency invoice is entered by hand (`add_erp_manual_invoice.php`, see erp-manual-invoice-draft-issue).

## How is the exchange difference posted?
- `erp_fx_post_difference()` runs when an invoice becomes `paid`, inside the `erp_post_receipt()` transaction: one `kind='fx_diff'` row (`doc_type='fx_diff'`, `doc_id` = invoice as idempotency key).
- Amount = `Σ settlement.amount_base − (grand_total_base − Σ return.grand_total_base)`.
- Reversal when a foreign invoice leaves `paid`: see erp-receipt-corrections-and-gift-card.

Related: docs/memory/erp/erp-manual-invoice-draft-issue.md
