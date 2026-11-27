---
name: accounting-sync-log
description: A failed accounting sync send (AS5): Events card with Try again on Xero's Connections page, the line on View Order and the ERP invoice, the daily alert, the reauth hold
module: accounting_sync_log
---

## Where is it?
- `includes/fn/accounting_sync_log.php`, loaded where used (Connections, View Order, the ERP invoice, the alert's source); tests `tests/accounting_sync_log_test.php`. It reads `accounting_sync` (`accounting-sync-queue.md`) and writes only Try again.

## What does Xero's Connections page show?
- An "Events" card after "Connection details" (`pg_accounting_sync_log_card()`), under the Site Settings › Accounting gate (`connections_accounting_may_manage()`), while the site books into an organisation. Laid out as the ad conversions' Events card (`ad-conversions-log.md`).
- "Daily counts": the last 7 days, "n sent, n failed", a rose "All failed" pill on a day with failures and nothing sent. A sent row counts on its `sent_at` day, a failed one on its `updated_at` day.
- "Last events": the 50 newest sale, payment and refund rows of the organisation (pairings never), by `updated_at`: the kind, the time, the service's reason for a failure or a retry, the state pill ("Waiting to be sent", "Tried again later" gray, "Sent" teal, "Failed" rose), the order or ERP invoice linked; a receipt has no link.
- Try again (quiet button, failed rows only): POST `connections_action=accounting_sync_again`, `sync_row`; puts the row back to waiting, due now, attempts 0, only a failed sale/payment/refund of the current organisation.

## What line does a record get?
- View Order (Payment card) and the ERP sales invoice or return (its side card), labelled with the service's name: "Sent to Xero" teal, "Waiting to be sent" gray, "Not sent: <reason>" rose (`pg_accounting_sync_record_line()`).
- With the ERP on, View Order reads the order's ERP invoice; an order without one says "Not sent: the order has no ERP invoice.". With the ERP off, an order with no row (unpaid, or paid before the connection) shows no line.

## When does the operations alert go?
- Code `accounting_sync_failed` (warning, opens `connections.php?service=xero`), source `accounting_sync` in `pg_ops_alert_sources()` (`pg_ops_alerts_check_accounting_sync()` loads the module): once a day, for yesterday, when sends failed and none was sent. The day is claimed in the alert state file (`accounting_sync` key); the subject is the service. The detail names the service, the day, the count and the last reason.

## What does a connection to be connected again do?
- When the Xero connection is marked `reauth` (`pg_connect_mark_reauth()`), `pg_accounting_sync_dispatch()` sends nothing (`pg_accounting_sync_held()`); rows are still written and wait. The Events card says "Sending is held" with the amber "Must be connected again" pill; Connections' attention block offers Connect again as for every account.

## What was not tried?
- No call to Xero (no test keys) and no database run of the reads; the card, the lines and the alert are covered by unit tests of their pure parts.
