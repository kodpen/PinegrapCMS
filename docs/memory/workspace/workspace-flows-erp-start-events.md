---
name: workspace-flows-erp-start-events
description: ERP events a flow can start on (flows-next C3): invoices, receipts, incoming e-documents, quote decisions; the new erp.invoice.overdue and erp.edoc.received
---

## Which ERP events start flows?
- `erp.invoice.created`, `.paid`, `.overdue`, `.edoc_changed` (the invoice's e-document answered: sent, accepted or rejected), `erp.receipt.created`, `erp.edoc.received`, `erp.quote.accepted`, `.rejected`. In `pg_event_workspace_events()`, `ws_events_catalog()` (only with `ERP_ENABLED`) and `ws_flow_event_outputs()`. Every other ERP event stays a webhook only.

## How do they reach the Workspace?
- `erp_event()` (includes/erp/events.php) calls `pg_event_record()` itself, then `api_webhook_enqueue(..., true)` so the queue does not keep it twice; it reaches the Workspace even where the API's webhook file is missing.

## Which are new, and where are they announced?
- `erp.invoice.overdue`: `erp_overdue_check()` (includes/erp/notify.php), for each sales invoice the overdue digest lists for the first time, after the `overdue_notified_at` stamp; carries `days_overdue`. Only while the overdue reminders are on (`erp_overdue_notify_enabled()`): there is no other first-overdue stamp, and adding one is a schema change.
- `erp.edoc.received`: `erp_edoc_inbox_keep()` (includes/erp/edoc/inbox.php), only when the row is inserted (`added`), never when read again; payload the inbox row's id, `gib_uuid` and its columns. The first read of a provider may add many documents at once; a flow's starts are capped by `WS_SCHEDULED_CHAIN_HOURLY`.
- Both are in `erp_webhook_events()` and have audit labels; the audit trail labels an e-document by supplier and number.

## Record, right, outputs?
- Records by payload `id`: `invoice`, `receipt` (the till movement), `edoc` (the inbox row), `erp_quote`. Right `erp`; a receipt `erp_cash`.
- `pg_event_workspace_payload()` lays an invoice's nested `edoc.status` flat as `edoc_status`.
- Outputs are single values; amounts in minor units (`grand_total`, `paid_total`, `amount`, `total`) are not offered.
- Tests: `tests/workspace_erp_start_events_test.php`; parity lines in `tests/workspace_flows_parity_test.php`.
