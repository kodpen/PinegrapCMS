---
name: bug-hunt-back-in-stock-notices
description: Bug hunt of back-in-stock notices and the event waiting list (PR #10712): three faults fixed, one issue opened (#10713), what was read clean
module: stock_notify
---

## Who gave the area, and what was it?
- Architect (2026-11-20, agent channel message 10509): back-in-stock notices, `includes/fn/stock_notify.php` and `stock_notify.php`, with `commerce/back-in-stock-notices.md`, `back-in-stock-sms.md`, `event-tickets-waiting-list.md`. Not hunted before.

## What was fixed (PR #10712, commerce/back-in-stock-send-retry-shared-dates.md)?
- A recurring event whose dates share their places was never mailed once its first date was over: its waiting rows are on date 0 and the sender read the places on date 0, the first date, which had ended (`pg_stock_notify_event_send()`). Now read on the series' last date; the mail and the export name no date.
- A mail sent in the request (no mail job) that failed left its row claimed: the visitor was never told. The row now waits again (`pg_stock_notify_unclaim()`), in both senders, unless the failure is for good or a device or WhatsApp message told the visitor.
- The waiting list's limit answer said "waiting for many products"; it now says waiting lists.

## Which issues were opened?
- #10713: a visitor cannot withdraw a request; no link in the answer or the mail (needs a product decision).

## What was read clean?
- Request rules and order of statuses, the number held like the address, the 10 / 10 min rate limit after the token check, shared with the waiting list.
- Claim then send: conditional UPDATE, one affected row, one mail per address and one text per number in a batch.
- The `inventory.changed` listener at 0→n→0: sales are ignored, the product is read again before sending, a stock gone again sends nothing and leaves rows waiting.
- The one-text promise: the endpoint promises a text only under `pg_stock_notify_sms_sends()`, the sender's own rule; WhatsApp and push go beside it by decision.
- Event dates and seats: separate dates normalised by `pg_stock_notify_event_date()` in the request, the cancel and Edit Event; an overbooked date freed by one place stays full.
- Export (address, account, event name and date), the account, product and event delete hooks, the daily trim and its batches.
