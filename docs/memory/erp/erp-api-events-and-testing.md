---
name: erp-api-events-and-testing
description: The ERP event door erp_event() (webhook queue, audit trail, alerts), where writers announce, what a new event needs, and how to test the ERP API on dev without creating keys.
---

## What does erp_event() do? (`includes/erp/events.php`)
- The one door for every ERP announcement. It feeds the audit trail (`erp_audit_event()`, 4.90: every event, API installed or not), the bell and device notices (`erp_alert_event()`, 4.91: only what the store asked for) and the webhook queue (`api_webhook_enqueue()`).
- Returns the rows queued: 0 when the API file is missing, or when the name is not declared in `erp_webhook_events()` (`includes/erp/api.php`; the queue drops it without a word). It never stops the writer.
- The payload builders (`erp_event_invoice()`, `erp_event_receipt()`, `erp_event_account()`, `erp_event_waybill()`, `erp_event_expense()`, `erp_event_quote()`, `erp_event_purchase_order()`, `erp_event_stock_count()`) read the record again, so every subscriber sees one shape whichever screen or endpoint caused it.
- `erp_event_invoice()` also starts side work: the kept PDF (`erp_archive_defer()`, on created), the purchase order's status (`erp_purchase_order_refresh_for_invoice()`, created/cancelled), the automatic e-mail (`erp_mail_auto_consider()`, created/edoc_changed). A path that writes an invoice without this event skips all three.

## Where does a writer announce?
- Invoices, returns, receipts: right after the writer's own `erp_tx_commit()`, before the success return. Expenses: inside the transaction once the row has its final state. Stock count: after its commit.
- Nested in an outer ERP transaction, `erp_tx_commit()` only lowers the depth (`includes/erp/ledger.php`), so the queue row rides on the same connection and a rollback removes it (erp_settle → refresh_paid).
- `erp.invoice.paid` fires only when `erp_invoice_refresh_paid()` actually moves the status to paid (old status ≠ paid). `erp.invoice.edoc_changed`: erp-isbasi-poll-documents-screens.md.

## What does a new event need?
- A row in `erp_webhook_events()`, an `erp_event_<object>()` payload builder, the call in every writer (screen, API, job).
- The audit trail takes the object type from the name's second part (`erp.<type>.<action>`): a new type needs its label and amount in `erp_audit_event()`, a row in `erp_audit_object_types()` and its sentence in `erp_audit_event_text()` (erp-audit-trail.md).
- A bell or device notice only where the store wants to hear at once: erp-alerts.md.

## How is the ERP API tested on dev?
- No keys are created for a test: the owner runs the real-key test. Verify the scope ceiling from a probe with `api_load_owner()` + `api_owner_scopes()` + `api_effective_scopes()`.
- If the real-key path is closed by the ceiling, use a session probe: `PG_API_ENTRY` + `init.php` + `validate_erp_access` + API bootstrap + `api_modules_load()`, a fake `api_current_app()` with all scopes, then `api_route_match` → `api_validate_input` → handler.
- `api_send()` calls `exit`; `api_log_request` writes a log row with application 0 (acceptable on dev).
- After an event test that added an event to someone else's subscription, restore it and delete the queue rows.

## Where are the PATCH rules?
- Partial updates, `also_accepts` and the IIS 405 rule: erp-account-save-partial-writes.md.

Related: docs/memory/erp/erp-api-seam.md
