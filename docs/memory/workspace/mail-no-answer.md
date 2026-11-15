---
name: mail-no-answer
description: Remind me if no answer comes (IB10b): the field on Reply by e-mail and New e-mail, the due time from the sending, inbox kind mail_no_answer, "No answer yet" on the writer's row and pane, what ends it
---

## What does the person see?
- "Remind me if no answer comes" (Yanıt gelmezse hatırlat) on Reply by e-mail and New e-mail (and Forward as e-mail, which is New e-mail's form): No, In 1 day, In 3 days, In 7 days. Not kept in a draft.
- With no incoming mail in the conversation by then, the writer gets inbox kind `mail_no_answer` ("No answer yet: {subject}", the customer as body, hourglass icon, opens `messages_mail.php?conversation=`).
- From then on the writer's list row and the pane's heading show the yellow badge "No answer yet" (`ws_mail_no_answer_badge_html()`) until an answer comes; reading the conversation does not clear it. Nobody else sees it.

## How does it work?
- The panel's actions `ws_mail_reply` and `ws_mail_new` call `ws_mail_no_answer_set()` after a stored mail with the form's `remind_days` (`ws_mail_no_answer_days()`: 1, 3 or 7, anything else none). The API and MCP send none.
- Due = the days from the sending (`ws_mail_no_answer_due()`): a mail sent later, or waiting for Undo (`mail-undo-send.md`), counts from its `send_at`.
- One row per writer and conversation in `ws_mail_reminders`, kind `no_answer` (`mail-remind-later.md` holds the table); a later mail with the choice moves it to the new item and time.
- Woken by the same run as Remind me later (`ws_mail_reminders_run()`, both kinds). A mail undone into a draft, cancelled before its time, or whose item row is gone because the undone draft was deleted or sent again (`ws_mail_no_answer_left(null)`, #9684), or a channel the writer can no longer read, drops the row with nothing told.
- Any incoming mail in the conversation deletes its no-answer rows, waiting or told (`ws_mail_remind_new_mail()` in `ws_mail_ingest()`).

## Schema?
- None of its own: IB10's table (step 11.0.212), owner 2026-11-15.

## Tests?
- `tests/workspace_mail_remind_test.php`: the days, the due time from the sending, which states still wait, the badge, the wiring. Sandbox: set from Reply by e-mail, due 3 days on; woken by the job, the badge on row and pane; a customer reply removed it.
