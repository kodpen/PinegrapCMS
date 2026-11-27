---
name: xero-tax-rates
description: Tax rates matched on Xero's Connections page after Ledger accounts: the site rates listed, the Xero rates offered, the match kept as an accounting_sync tax pairing
module: xero, xero_page, accounting_sync
---

## Where are the tax rates matched?
- `connections.php?service=xero`, Connection details, "Tax rates", after Ledger accounts; same gate as the other forms there (Site Settings › Accounting, `connections_accounting_may_manage()`). Action `xero_tax_rates`, saved by `pg_xero_page_save_tax_rates()`.
- Shown once an organisation is chosen and the 11.0.284 step has run (`pg_accounting_sync_target()` not null); otherwise a line says why.

## Which site rates are listed?
- `pg_accounting_sync_site_tax_rates()`: every `tax_zones.tax_rate`, every non-NULL `products.tax_rate`, 0% always (an untaxed or exempt line still carries a rate), and with "Rates by the buyer's country" on each EU state's standard rate today. Ids from `pg_accounting_sync_tax_local_ids()` (thousandths, unique, smallest first); label `pg_accounting_sync_tax_label()` ("8.875%").
- Not listed: rates on old order lines or ERP invoice lines that no zone or product carries any more. A sale with such a rate waits unmatched; adding the rate to a zone or product lists it.

## Which Xero rates are offered?
- `GET /api.xro/2.0/TaxRates` (`xero_tax_rates_read()`), read when the page is drawn and again on save. Offered: Status ACTIVE and CanApplyToRevenue (`xero_tax_rates_for_sales()`), label "Name — rate%". Save refuses a TaxType not offered. A kept TaxType no longer offered shows a note under its field.
- No automatic match by rate: the owner of the books picks, as two Xero rates may share a percentage (UK 20% VAT on Income vs. reverse charge).

## How is a match kept?
- One `accounting_sync` row: kind `tax`, local_table `tax_rate`, local_id the rate id, remote_id the Xero TaxType, status sent, through `pg_accounting_sync_pair_save()`. Keyed by organisation, so another organisation starts unmatched.
- "Not matched" deletes the row (`pg_accounting_sync_pair_drop()`, which drops only pairing kinds customer, item, tax). So a tax row present always has a TaxType; the driver reads it with `pg_accounting_sync_pair()` and answers `wait` when it is missing.

## What was not tried live?
- No real Xero organisation was read; the answer's shape is Xero's OpenAPI (`plans/accounting-sync-census-xero-sale.md`). Tests: `tests/xero_test.php` (parse, offered, label), `tests/accounting_sync_test.php` (ids, label).
