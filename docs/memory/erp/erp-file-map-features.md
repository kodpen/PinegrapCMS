---
name: erp-file-map-features
description: Where ERP work lives, part 2: stock, accountant pack, period lock, expenses, reports, rules, quotes, purchase orders, recurring documents, prices, cheques, bank statements, storefront, CSV.
---

## Where are the later ERP features? (paths under `includes/erp/` unless given)
| Work | Files |
|---|---|
| Stock moves, cost | `stock.php`, `erp_stock.php` |
| Accountant pack, link, monthly job | `accountant.php`, `erp_accountant*.php` |
| Period lock | `lock.php` |
| Expenses | `expenses.php`, `erp_expenses.php`, `add_erp_expense.php`, `edit_erp_expense.php`, `erp_expense_categories.php` |
| Recurring expenses | `expense_recurring.php`, `erp_expense_recurring_job.php` |
| Profit and loss | `profit.php`, `erp_profit.php` |
| VAT report | `vat_report.php`, `erp_vat_report.php` |
| Accounting rules | `rules.php`, card in `erp_settings.php` |
| Credit limit | `credit.php` |
| Minimum stock | `stock_levels.php`, `erp_stock_minimums.php` |
| Audit trail | `audit.php`, `erp_audit.php` |
| Instant alerts | `alerts.php`, `erp_stock_alert_job.php` |
| Quotes | `quotes.php`, `erp_quotes.php`, `add_erp_quote.php`, `edit_erp_quote.php`, `get_erp_quote_pdf.php` |
| Purchase orders | `purchase_orders.php`, `erp_purchase_orders.php`, `add_erp_purchase_order.php`, `edit_erp_purchase_order.php`, `get_erp_purchase_order_pdf.php`, `templates/purchase_order.html` |
| Document signature (quote) | `signatures.php` |
| Recurring invoices | `invoice_recurring.php`, `erp_invoice_recurrences.php`, `erp_invoice_recurring_job.php` |
| Account prices | `price_lists.php`, `erp_account_prices.php`; in the shop (setting `erp_storefront_account_prices`) the rule `erp_storefront_price()` (`storefront.php`), applied by `pg_erp_member_prices()` (`includes/fn/ecommerce.php`) |
| Pay on account at checkout | setting `erp_open_account_payment`; rule `erp_storefront_on_account()` (`storefront.php`); eligibility and reason `pg_erp_open_account()`, `pg_erp_open_account_refusal()`, `pg_erp_open_account_option()` (`includes/fn/ecommerce.php`); checked and written in `submit_order.php` (`orders.erp_on_account`) |
| Stock count | `stock_counts.php`, `erp_stock_counts.php`, `erp_stock_count.php` |
| Cheques and notes | `cheques.php`, `erp_cheques.php`, `erp_cheque.php` |
| Bank statement | `bank_import.php`, `erp_bank_statements.php`, `erp_bank_statement.php` |
| Late payment fee | `late_fee.php`, `add_erp_late_fee_invoice.php`; settings card `erp_settings.php`; shown on `edit_erp_account.php`, `erp_reconciliation.php` |
| Cash forecast | `cash_forecast.php`, `erp_cashflow.php?view=forecast` |
| Counter sale (ERP side) | `includes/local_sale.php` (split payment `local_sale_split_parts()`) |
| Customer's own account in the shop | `storefront.php` (documents, balance, ownership), `get_account_document.php` (the customer's PDFs: documents and statement; another's is a 404), widget `account_invoices` (`includes/fn/widgets_account.php`); the member's account without loading the ERP: `pg_erp_member_account()` (`includes/fn/ecommerce.php`) |
| CSV import and export | `import.php`, `export.php`, `erp_accounts_import.php`, `erp_export.php` |
| Dashboard | `erp_dashboard.php` |
