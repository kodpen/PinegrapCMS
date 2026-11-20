---
name: back-in-stock-send-retry-shared-dates
description: Back-in-stock and waiting list senders (PR #10712): a mail failed in the request waits again; a series sharing its places is read on its last date and named without a date
module: stock_notify
---

## What happens to a row whose mail failed?
- Both senders (`pg_stock_notify_send()`, `pg_stock_notify_event_send()`) claim a row, then mail. When `email()` sent in the request (no mail job) and failed, the row goes back to waiting (`pg_stock_notify_unclaim()`): the next announcement or freed place tries it again.
- Not when the failure is for good (`pg_mail_last_error_permanent()`, e.g. no way to send at all), and, for a product, not when a device notification or a WhatsApp message already told the visitor (`pg_stock_notify_mail_unsent()`).
- Symptom fixed (#10712): the row stayed claimed, so the visitor was never told.

## How is a series sharing its places read?
- A recurring event with separate reservations off (`pg_stock_notify_event_shared()`) keeps its waiting rows on date 0, which names no date.
- `pg_stock_notify_event_send()` reads its places on the series' last date (`pg_stock_notify_event_read_occurrence()`: `total_recurrence_number`), so the list is mailed until the series ends. The mail names the event alone; the export's `event_date` is empty (`pg_stock_notify_event_start()`).
- Symptom fixed (#10712): date 0 was the first date; once it ended the state read `none` and nobody was mailed, and the mail and export named the first date.

## What does the waiting list's limit say?
- "You are already on many waiting lists. Please try again later." (#10712); it said "waiting for many products".

Test: `tests/stock_notify_test.php`, `tests/event_waiting_list_test.php`.

Related: docs/memory/commerce/back-in-stock-notices.md, docs/memory/commerce/event-tickets-waiting-list.md
