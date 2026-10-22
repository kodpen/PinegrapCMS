---
name: erp-file-map
description: Where ERP work lives, part 1: the core (entry, money, ledger, accounts, settlement, orders, invoices, waybills, documents, aging, e-documents, API seam). Part 2: erp-file-map-features.md.
---

## Where is the ERP core? (paths under `includes/erp/` unless given)
| Work | Files |
|---|---|
| Entry point, load order, gate `PG_ERP_ENTRY` | `bootstrap.php` |
| Money, rounding | `money.php` |
| Exchange rate, currency gate | `fx.php` |
| Ledger core (`erp_query`, `erp_tx_*`, account posting and balance cache) | `ledger.php` |
| Tills, receipts, transfers (the only cash writer) | `cash.php` |
| Account card, due date, snapshot, country rules | `accounts.php`, form `account_form.php`, New account dialog `account_quick.php` + `erp_account_quick.php` |
| Settlement, `erp_invoice_refresh_paid()`, gift card | `settlement.php` |
| Document numbers, series | `numbering.php` |
| Order → invoice | `order_bridge.php` |
| Order documents card, cancel and refund steps | `order_flow.php`, `erp_order_flow.php` |
| Card refund by line and quantity | `order_refund.php` |
| Returns, cancellation | `returns.php` |
| Typed invoice logic; form and POST reading; line editor | `invoice_manual.php`; `invoice_form.php`; `assets/js/erp_invoice_editor.js` |
| Catalogue, campaign, barcode for lines | `products.php`, `get_erp_products.php` |
| VAT withholding | `withholding.php` |
| Delivery notes (internal waybill) | `waybills.php`, `waybill_form.php` |
| Template + PDF | `document.php`, `templates/*.html` |
| Kept documents; file manager folders | `archive.php`; `files.php` |
| Side drawer of the lists | `drawer.php`, `get_erp_drawer.php` |
| Due dates and aging | `aging.php`, `erp_aging.php` |
| Overdue notices | `notify.php`, `erp_overdue_job.php` |
| Reconciliation letter | `reconciliation.php`, `erp_reconciliation.php`, `get_erp_reconciliation_pdf.php` |
| Cash flow | `cashflow.php`, `erp_cashflow.php` |
| Invoice e-mail | `mail.php` |
| Events (webhooks) | `events.php` |
| API seam: route table; handlers | `api.php`; `api_resources.php` (reads, accounts, receipts, expenses), `api_documents.php` (invoice, quote, recurring invoice writes), `api_stock.php` (waybill, stock count, minimums), `api_cash.php` (transfers, cheques), `api_reports.php` (profit, cash flow, forecast, late fee and its draft, dashboard), `api_purchase_orders.php` |
| e-Document layer | `edoc/registry.php`, `edoc/service.php`, `edoc/<code>.php` |
| Incoming e-invoices, UBL | `edoc/inbox.php`, `edoc/ubl.php`, `erp_inbox*.php`, `get_erp_inbox_file.php` |
| Account sync (provider cards) | `edoc/account_sync.php`, `erp_account_sync*.php` |
| Screens | root `erp_*.php`, `add_erp_*.php`, `edit_erp_*.php`, `get_erp_*.php` |
