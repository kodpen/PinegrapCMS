---
name: accounting-sync-census-proposal
description: QuickBooks and Xero sync census, proposal: out only, what is sent at which moment, sending from a queue in the job, pairing in a new table or columns
---

## Which way does data go?
- Out only. The site writes sales, customers, items and payments to QuickBooks Online or Xero and reads back only ids, sync tokens and the answers to its own calls. Nothing changed in the books comes back to the shop or the ERP; webhooks and change data capture are not used.

## How does a site connect?
- One provider file each (`includes/connect/providers/quickbooks.php`, `xero.php`) and one purpose owned by the site, as Paraşüt (`accounting-sync-census-parasut.md`), model A: the site's owner registers their own app.
- The company (QuickBooks `realmId` from the return, Xero tenant from `/connections`) and its name go into a sealed service entry (`pg_connect_service_put()`), not a `config` column. One accounting service per site at a time, as two would book each sale twice.

## What is sent when?
- Without the ERP (`accounting-sync-census-source.md`): when the order is paid, a SalesReceipt in QuickBooks, an AUTHORISED invoice and a payment to a clearing account in Xero; each `order_refunds` row as a RefundReceipt, or a credit note paid out. An order cancelled before payment sends nothing.
- With the ERP: `erp.invoice.created` as an Invoice (Xero ACCREC, AUTHORISED); `erp.receipt.created` as a Payment against it; a return invoice as a CreditMemo or credit note; `erp.invoice.cancelled` as a void.
- Before a sale its customer and items are found or made and paired. Each local tax rate is matched once to a tax code of the service, and the income, clearing and bank accounts are chosen, on the service's Connections page; a sale with an unmatched rate waits.
- From a queue, never in the request: the moment writes a row, `job.php` sends, as ad conversions do (`pg_ad_conversion_dispatch()`, `job.php` 1196; `commerce/marketplace-outbound-layer.md`). Paced to each service's limits, with a `requestid` (QuickBooks) or `Idempotency-Key` (Xero) made from the row so that a retry is never a second sale.
- Only sales after the connection; earlier ones only on the owner's yes.

## Where is the pairing kept: a table?
- Proposed: one new table, asked of the owner (Fixed rules 5): service, company, kind (customer, item, tax, sale, payment, refund), local table and id, remote id, sync token, status (waiting, sent, failed), attempts, next try, last error, times; unique on service, company, kind, local table and id.
- Why: one row is both the queue and the pairing, as `ad_conversion_events` is (`workflow/decisions-made-schema-35.md`); both services and a change of company fit with no new column; the failures list and the Events card read one table.
- `erp_invoices.edoc_*` and `erp_edoc_accounts` are not reused: they belong to the e-document provider, which a Turkish site may keep beside a bookkeeping service.

## Or columns?
- One column per service and record (`orders`, `order_refunds`, `products`, `erp_invoices`, `erp_accounts`, the customer): about a dozen columns now and as many more for each new service, plus a separate place for the queue and the error. It is Paraşüt's pattern, which `commerce/marketplace-outbound-layer.md` says is not repeated.
