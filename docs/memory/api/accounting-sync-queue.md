---
name: accounting-sync-queue
description: Accounting sync queue and pairing (table accounting_sync, 11.0.284): what writes a row, the general job's pass, the driver's answer, retries, the key against a second sale, what is not sent
module: accounting_sync
---

## What is the table?
- `accounting_sync` (step 11.0.284, approved in `workflow/decisions-made-schema-54.md`): one row per local record and what it became in a service (`service` `xero`, later `quickbooks`) within one company (`company`: Xero tenant id, QuickBooks realm). Unique on service, company, kind, local_table, local_id.
- A row is both the queue (`status` waiting/sent/failed, `attempts`, `next_try_at`, `last_error`) and the pairing (`remote_id`, `sync_token`). Nothing is added to orders, products or invoices.
- `kind`: sale, payment, refund are queued; customer, item, tax are pairings kept as sent (`pg_accounting_sync_pair_save()`). A tax rate's pairing is local_table `tax_rate`, local_id the rate in thousandths (`pg_accounting_sync_tax_local_id()`, 20% = 20000); it is written on Xero's Connections page and deleted when unmatched (`xero-tax-rates.md`).
- Only ids, remote ids and error text: no personal column, so it is not in the privacy erase or export lists.

## What writes a row?
- ERP module off: `pg_order_mark_paid()` and `pg_order_complete()` when it stamps `paid_at` write a sale and a payment for (`orders`, id). The general job's refund scan reads `order_refunds` after a cursor kept in `data/temp/accounting_sync_refunds.txt`, 200 at a time, and writes a refund for (`order_refunds`, id) when the order has a sale row and the amount is above 0.
- ERP module on: `erp_event()` calls `pg_accounting_sync_erp_event()`. A sales invoice issued writes a sale, a return invoice a refund (`erp_invoices`); a receipt with an `invoice_id` writes a payment (`erp_cash_transactions`). A payment, and a return naming a `parent_invoice_id`, is written only when that invoice has a sale or refund row. A cancellation puts the existing row back to waiting with attempts 0 (its driver voids); it never writes a row. Proforma, purchase and draft write nothing.
- Rows are written only while the site books into a service (`pg_accounting_sync_target()`, `quickbooks-connection.md`). Another organisation starts with rows of its own.

## How is a row sent?
- `job.php`, after the ad services: `pg_accounting_sync_queue_refunds()` then `pg_accounting_sync_dispatch(10)` (10 seconds). Due rows of the current company, oldest first, at the service's pace (`pg_accounting_sync_pace()`: Xero 10 rows a pass, QuickBooks 40). A row is claimed (next_try_at moved 600 s ahead, matched on its old value) before its call.
- Driver: `includes/fn/accounting_sync_<service>.php` with `pg_accounting_sync_send_<service>(array $row, array $target)`. Without the file, or while the connection is `reauth` (`accounting-sync-log.md`), the rows wait. Xero's: `xero-sync-driver.md`.
- The driver's answer: `ok` with `remote_id`, `sync_token`; or `wait` seconds (not ready: a sale not sent yet, a tax rate not matched), no attempt counted, a day at most; or `status`, `error`, `retry_after`, `final` as `api_http_post()` answers.
- 429 waits Retry-After, a minute at least, no attempt counted. 0, 408, 5xx retry on `api_webhook_backoff()` until `api_webhook_max_attempts()`. Other refusals, `final` and the last attempt fail. The pass stops for the service on 0, 401 or 429.

## What stops a second sale?
- `pg_accounting_sync_key($row, $operation)`: `pg-` and 47 hex of an HMAC of service, company, kind, local_table, local_id and the operation under `ENCRYPTION_KEY`. The same on every try; 50 characters (QuickBooks `requestid` limit, inside Xero's 128 for `Idempotency-Key`). With the unique key, one record is one row and one sale.

## What is not sent?
- A counter sale with the ERP off (it stamps no `paid_at`; with the ERP on its invoice and receipt are sent), an ERP receipt not linked to an invoice, and with the ERP on and automatic invoices off an order without an ERP invoice.
- Nothing comes back from the service.
