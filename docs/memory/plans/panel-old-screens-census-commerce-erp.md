---
name: panel-old-screens-census-commerce-erp
description: LM1a census, ERP groups E1–E5: invoice and quote documents, lists off the list table driver, ERP reports, records, imports and settings; what each draws its own way, estimated product lines
---

## E1 Invoice, quote and order documents (~850 lines)?
- Edit Invoice `edit_erp_invoice` (1,823 lines, ~250): titles are `pg-title-section` already; 19 Bootstrap buttons, 8 inline styles, 6 tables, 23 `small`.
- `includes/erp/invoice_form.php` (~140 with its roots): 19 inline styles, an old title, no title recipe; used by Create Manual Invoice `add_erp_manual_invoice`, Create Purchase Order `add_erp_purchase_order`, Create Quote `add_erp_quote`, Edit Invoice Draft `edit_erp_invoice_draft`, which have no `pg-measure-edit` (class `erp erp_invoices`).
- Edit Quote `edit_erp_quote` + `includes/erp/quote_templates.php` (~110, old titles); Edit Purchase Order `edit_erp_purchase_order` (~90, 4 tables, old titles).
- Delivery note: `add_erp_waybill`, `edit_erp_waybill`, `includes/erp/waybill_form.php` (~130). The side drawer `includes/erp/drawer.php` (~70): own `<style>` block, 4 tables, 21 old classes.

## E2 and E2b ERP lists off the driver (~1,000 lines, two steps)?
- All named in `ui/look-screen-widths.md` (#9230); each prints a `table[data-pg-sort]` in a card with an old title, some with a figures strip of `small text-uppercase fg-secondary` labels.
- E2 (~550): Expenses `erp_expenses` (figures strip, the list, By category, Repeating expenses cards), Expense Categories `erp_expense_categories` (9 inline styles), Cheques `erp_cheques`, Bank Statements `erp_bank_statements`.
- E2b (~450): Purchase Orders `erp_purchase_orders`, Quotes `erp_quotes`, Recurring Invoices `erp_invoice_recurrences`, Stock Counts `erp_stock_counts`, Audit Log `erp_audit`.
- Leftovers on driver lists join E2b: cards with old titles in `erp_account_prices`, `erp_stock` (the movements ledger stays a table), `erp_stock_minimums`, `erp_account_sync`.

## E3 ERP reports (~650 lines)?
- At the report width already (#9249); they lack the report recipes (`ui/look-report-screens.md`): figures, `pg-report-frame`, `pg-report-table`.
- ERP Dashboard `erp_dashboard` (7 inline styles, 5 tables, old titles), Cash Flow `erp_cashflow` (two figures strips, 14 old titles, 5 tables), Profit `erp_profit` (4 tables, 22 cards, a literal size), VAT report `erp_vat_report` (frames in part, 6 tables), Aging `erp_aging` (one table).

## E4 ERP records (~650 lines)?
- Edit Expense `edit_erp_expense` (27 old classes) and Create Expense `add_erp_expense`; Receipt `erp_receipt` and `add_erp_receipt`; Cheque `erp_cheque`; Create Return `add_erp_return` (a table); Transfer `add_erp_transfer`.
- Account: `edit_erp_account`, `add_erp_account`, `includes/erp/account_form.php` (32 flagged, 11 old classes); Till: `edit_erp_till`, `add_erp_till`, `includes/erp/till_form.php`.

## E5 ERP imports, matching and settings (~850 lines)?
- Done in #9704.
- Account Sync item `erp_account_sync_item` (37 old classes, 3 tables), Bank Statement `erp_bank_statement`, Reconciliation `erp_reconciliation`, Incoming e-Invoice `erp_inbox_document`, Stock Count `erp_stock_count`.
- ERP Settings `erp_settings` (7 old titles, 18 cards, not the settings cards `pg-set-card`), Import Accounts `erp_accounts_import` (48 old classes), Export `erp_export`, Accountant `erp_accountant`.
