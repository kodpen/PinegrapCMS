---
name: sms-erp-overdue
description: ERP overdue receivable SMS (PR #3626): event erp_overdue (kind reminder, off by default), one text per account per notice, which number, the reference, digest count, invoice screen line
---

## What is sent, and when?
- Event `erp_overdue` in `pg_sms_message_events()` (kind `reminder`, not commercial, no İYS consent): fields `^^store^^`, `^^account^^` (account title), `^^count^^` (documents in this run), `^^total^^` (their open base amount, `erp_money_out()`). Off until switched on (Settings › Communication › SMS Messages).
- Queued by `erp_overdue_notify_customers()` → `erp_overdue_sms_queue()` (`includes/erp/notify.php`) in the overdue digest run, one text per account, at the first notice and again at the second (`erp_overdue_sms_stage()`: `second` when any of the account's documents was announced before). The stamps `overdue_notified_at` / `overdue_second_notified_at` already make each stage run once; no SMS column.
- The e-mail store switch `ERP_OVERDUE_NOTIFY_CUSTOMER` does not gate it: the SMS event is its own opt-in. The account switch `overdue_notify_customer` does. An account without an e-mail address is still texted. Documents with no account (account_id 0) are not.

## Which number?
- `erp_overdue_sms_decide()` (pure): the account's `phone` when `pg_sms_number()` reads it as a mobile, else the linked contact's `contacts.mobile_phone`. Reasons when none: `module_off`, `event_off`, `account_off`, `no_phone`, `not_mobile`.

## What is the reference?
- `erp_overdue:<account_id>:<first|second>:<Ymd of the run>` (`erp_overdue_sms_reference()`); the queue's UNIQUE reference keeps a repeated run that day from texting twice. `expires_at` two days, as every event.

## Where does the panel show it?
- The panel digest mail says how many texts were queued (`customers['texted']`); the CLI job prints it.
- The invoice screen's reminder card: `erp_overdue_sms_queued_at()` looks up the references built from the invoice's two stamps (both stages per stamp, since a first-time document can ride another document's second-notice run) in `sms_outbox`, failed rows ignored. Sent rows are swept after 7 days, so the line then disappears; the e-mail's own line stays.
- The card hides the event while `ERP_ENABLED` is off but carries its switch and text in hidden fields (`pg_sms_message_event_hidden_fields()`), so a save keeps them.

## How was it verified?
- `tests/erp_overdue_sms_test.php` (decision, stage, references), `tests/sms_messages_test.php` (event, render, hidden fields). Not run against a live provider.

Related: docs/memory/platform/sms-order-messages.md, docs/memory/erp/erp-overdue-second-notice.md, docs/memory/plans/sms-and-iys.md
