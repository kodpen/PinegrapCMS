---
name: erp-api-events-and-testing
description: ERP webhook events (erp_event, hook placement, new-event checklist) and how to test the ERP API on dev without creating keys.
---

## How do ERP events work? (`includes/erp/events.php`)
- `erp_event()` returns 0 if the API file is missing or the event is undefined; it breaks nothing.
- The hook sits INSIDE the function, right before the success return (after commit).
- `erp.invoice.paid` fires only when `erp_invoice_refresh_paid()` actually moves the status to paid (old status ≠ paid).
- An event fired inside a transaction (erp_settle → refresh_paid) is queued on the same connection, so a rollback removes it.
- A new event = three places: a row in `erp_webhook_events()`, an `erp_event_*()` payload, the hook. (`erp.invoice.edoc_changed` also exists: see erp-isbasi-poll-documents-screens.)

## How is the ERP API tested on dev?
- Creating keys (credentials) is not the agent's job: Erdal does the real-key test; the agent verifies the scope ceiling from a probe with `api_load_owner()` + `api_owner_scopes()` + `api_effective_scopes()`.
- If the real-key path is closed because of the ceiling, use a session probe: `PG_API_ENTRY` + `init.php` + `validate_erp_access` + API bootstrap + `api_modules_load()`, a fake `api_current_app()` with all scopes, then `api_route_match` → `api_validate_input` → handler.
- `api_send()` calls `exit`; `api_log_request` writes a log with application 0 (acceptable on dev).
- For an event test, if an event is added to someone else's subscription, restore its old state when done and delete the queue rows.

## Where are the PATCH rules?
- Partial update, `also_accepts` and the IIS 405 rule: see erp-account-save-partial-writes.

Related: docs/memory/erp/erp-api-seam.md
