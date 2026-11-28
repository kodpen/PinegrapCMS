---
name: quickbooks-sync-driver
description: QuickBooks Online's accounting sync driver: SalesReceipt, Invoice, Payment, RefundReceipt, CreditMemo, void, customers and items, requestid and SyncToken, its call spacing
module: accounting_sync_quickbooks
---

## Where is it?
- `includes/fn/accounting_sync_quickbooks.php`, `pg_accounting_sync_send_quickbooks($row, $target)`, found by `pg_accounting_sync_driver('quickbooks')` (`accounting-sync-queue.md`). It reads the environment, realm and accounts from `quickbooks_state()`; another realm answers 401 (the pass stops). No income account chosen: the row waits.
- It reuses the Xero driver's readers of orders, ERP invoices, buyers and refund shares, so both books get the same lines (`xero-sync-driver.md`).

## What does each row become?
- Sale, ERP off: a SalesReceipt of the order's lines, dated `paid_at`, CurrencyRef the base currency, paid into the bank (`Offline Payment`) or clearing account; PrivateNote "Order <number>", numbered by QuickBooks. Totals that disagree are refused. The order's payment row ends sent with no call.
- Sale, ERP on: an Invoice of `erp_invoice_items`, DocNumber `full_number` when it fits 21 characters (else in PrivateNote), its currency and due date. Withholding or a second tax is refused.
- Payment, ERP on: the receipt's settlement as a Payment linked to the Invoice; the invoice is read for its CustomerRef and CurrencyRef. A receipt paying a return fails with the reason: QuickBooks takes a credit memo's payout only in its own screens.
- Refund, ERP off: a RefundReceipt out of the payment's account; store credit and gift card refunds a CreditMemo (credit stays on the customer). Lines are the refund shared by the sale's rates, tax taken out. Refund, ERP on: the return invoice as a CreditMemo.
- A currency the company lacks is QuickBooks' 400: the row fails with its words.

## How are lines and tax given?
- Lines are SalesItemLineDetail, amount without tax (`line_total - discount_amount`); a quantity that does not divide the amount goes as 1 with "n × " before the description.
- US company (`quickbooks_tax_us()`): TaxCodeRef TAX on a taxed line, NON otherwise, `TxnTaxDetail.TotalTax` the site's total, no GlobalTaxCalculation.
- Elsewhere: TaxCodeRef the code matched on the Connections page (unmatched: waits 15 minutes), GlobalTaxCalculation TaxExcluded, and one TaxLine per rate (TaxRateRef, TaxPercent, NetAmountTaxable, the site's tax) when every code has a single sales rate; else QuickBooks works it out. A code's rate is read once a run.

## How are customers and items found?
- Customer: pairing `customer`, else `select Id from Customer where PrimaryEmailAddr = '…'`, else made. A DisplayName taken (6240) is tried once more with " (pg-contact-<id>)" or the e-mail. Guests are not paired.
- Item: pairing `item` (remote_id the Id, sync_token the name), else by Name, else made NonInventory on the income account. Name is the SKU when it fits 100 characters with no colon or quote, else `pg-<product id>`. Lines without a product (shipping, surcharges) use item `pg-charges`, paired as product 0.

## What keeps a retry from booking twice?
- Every write carries `requestid` = `pg_accounting_sync_key($row, operation)` (`create`, `void`, `contact`, `contact-2`, `item-<id>`); QuickBooks answers a repeat with the first write.
- The row keeps the record's Id and SyncToken. A cancelled ERP invoice or receipt is voided, a cancelled return's CreditMemo deleted; each first reads the current SyncToken (a payment changes it).

## How are QuickBooks' limits kept?
- Calls are 120 ms apart (10 a second allowed), at most 400 a minute a run (500 allowed); past that a call answers 429 and the pass stops. A 429 waits Retry-After, a minute at least. `pg_accounting_sync_quickbooks_pause()`.

## What was not tried live?
- No call to a real Intuit sandbox (no test keys): shapes from Intuit's pages (`quickbooks-service-facts.md`). Not verified: the PrimaryEmailAddr query, US TotalTax kept without TxnTaxCodeRef, CreditMemo delete. Tests: `tests/accounting_sync_quickbooks_test.php`.
