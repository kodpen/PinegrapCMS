---
name: xero-sync-driver
description: Xero's accounting sync driver: what each queued row becomes (invoice, payment, credit note, void), contacts and items, Idempotency-Key, limit headers
module: accounting_sync_xero
---

## Where is it?
- `includes/fn/accounting_sync_xero.php`, `pg_accounting_sync_send_xero($row, $target)`, found by `pg_accounting_sync_driver('xero')` in the general job's pass (`accounting-sync-queue.md`).
- It reads the organisation, connection and accounts from `xero_state()`; a state for another organisation answers 401 (the pass stops). No income account chosen: the row waits.

## What does each row become?
- Sale, ERP off (`orders`): an AUTHORISED ACCREC invoice from the order's lines as its ERP invoice would carry them (`erp_order_charged_items()` + `erp_order_lines()`, Paraşüt's way), dated `paid_at`, base currency, Reference the order number, numbered by Xero. Lines that do not come to the order's total are refused.
- Sale, ERP on (`erp_invoices`): the invoice's `erp_invoice_items`, InvoiceNumber `full_number`, its currency and due date (no CurrencyRate: Xero takes its day rate). Withholding or a second tax is refused.
- Lines: Exclusive, AccountID the income account, TaxType from the tax pairing, TaxAmount the site's own line tax.
- Payment, ERP off: the whole invoice total paid on `paid_at` once the sale is sent (else waits). ERP on: the receipt's settlement (`erp_settlements` through `erp_account_transactions` doc_id) pays the invoice, or the return's credit note when the receipt pays a return.
- Payment account: bank for `Offline Payment`, ERP `cash` and `transfer`; clearing for the rest (cards, payment services).
- Refund, ERP off (`order_refunds`): an Inclusive credit note, one line per tax rate of the sale, the refund shared by what each rate came to, then paid out from the payment account. Store credit and gift card refunds (type with `credit` or `gift_card`) are not paid out: the credit stays on the contact.
- Refund, ERP on: the return invoice as a credit note; its payout is the receipt that pays the return. A credit note is not allocated to the invoice it returns.
- A cancelled ERP invoice or return is VOIDED, a cancelled receipt's payment DELETED (`POST /Payments/{id}`); one never sent ends with no call. Xero refuses to void an invoice that still has payments: cancel the receipts first.

## How are contacts and items found?
- Contact: pairing `customer` (`contacts` contact_id for an order, `erp_accounts` for the ERP), else `GET /Contacts?where=EmailAddress=="…"`, else made with ContactNumber `pg-contact-<id>` / `pg-account-<id>`. A guest (contact_id 0) is not paired.
- Item: pairing `item` (`products`, remote_id ItemID, sync_token the code), else `GET /Items?where=Code=="…"`, else made, sold, not tracked as stock. Code is the SKU (`products.name`) when it fits 30 characters, else `pg-<product id>`. Shipping and surcharge lines have no item.

## What keeps a retry from booking twice?
- Every write carries `Idempotency-Key` from `pg_accounting_sync_key($row, operation)`: `create`, `void`, `contact`, `item-<id>`, `payout`. Xero keeps a key 6 minutes; then the pairings and remote_id stop a second record.
- A refund's credit note id is written to the row (`remote_id`) as soon as it is made; the payout's PaymentID is kept as `sync_token`, so a retry after a failed payout pays out only.

## How are Xero's limits kept?
- Each answer's `X-MinLimit-Remaining`, `X-AppMinLimit-Remaining` (2 or fewer: a minute) and `X-DayLimit-Remaining` (10 or fewer: an hour) hold further calls in the run (`pg_accounting_sync_xero_pause()`); a held call answers 429 so the pass stops. A 429 waits its Retry-After.

## What waits, what fails?
- Waits 15 minutes, no attempt: a rate not matched on the Connections page, accounts not chosen, a payment whose sale is not sent.
- Fails at once: the order or document gone, totals that disagree, withholding or second tax, any 4xx from Xero (a currency the books lack among them).
- Not tried live (no test keys): the shapes are Xero's accounting OpenAPI.
