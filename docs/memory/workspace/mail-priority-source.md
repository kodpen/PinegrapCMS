---
name: mail-priority-source
description: A support request's priority and source on its mail conversation (help centre HC7): columns, filter, pane select, first-answer hours per priority, the source label and its link back
---

## What is stored?
- Step 11.0.288 (`upgrade_2026_11_0_ws_mail_priority_source()`, owner's yes in `workflow/decisions-made-schema-54.md`): `ws_mail_conversations.priority` TINYINT (1 Low, 2 Normal, 3 High, 4 Urgent, default 2), `source` VARCHAR(10) (mail, whatsapp, sms, form, chat, account, default mail), `source_id` INT (the `forms` or `chat_conversations` row). Rows stored before read as Normal and mail; nothing is rewritten. No index: the list narrows by channel first.
- Code in `includes/workspace/mail_priority.php`. Until the step ran (`ws_mail_priority_ready()`, `pg_schema_has()`), nothing reads or writes the columns: no filter, no select, no source written.

## Where is the priority set and shown?
- Communication › Incoming e-mail only. The reading pane's Priority select sits after the status and holder selects (`state.priority` from `ws_mail_pane_tools()`, drawn by `state()` in workspace_mail.js); a channel's card has no select.
- Saved by the panel action `ws_mail_conversation` with `priority` 1..4 through `ws_mail_conversation_changes()` (`ws_mail_priority_change()`); error `priority` "That priority is not known.". The external API neither reads nor writes it yet.
- The list row shows a badge for Low, High and Urgent (Normal none) beside the state, only for a support box's conversation. Toolbar filter "Priority" (`priority=1..4`, "Any priority") beside Status and Held by, shown with them; it keeps to support boxes (`box_id NOT IN` the plain ones).

## What are the first-answer hours per priority?
- Box form, Answers tab, under "Support box", after "Resolve within (hours)": "First answer within (hours) by priority" with Low, High and Urgent fields (placeholder "As Normal"). Normal's is "First answer within (hours)" itself.
- Settings JSON key `priority_hours` `{"1":h,"3":h,"4":h}` (no column), at most `WS_MAIL_FIRST_REPLY_MAX`; empty or 0 follows the box's own target. Read by `ws_mail_priority_hours($box, $kept)` (a plain box answers none; `$kept` for its form), checked by `ws_mail_priority_hours_input()`, kept while the box is plain.
- `ws_mail_priority_reply_hours()` picks the target; used by the list (row time in red, "First answer overdue" filter), the channel card (`mail.php`) and the API's `first_reply_due_at` and `overdue` (`ws_mail_conversation_reply_hours()`). The resolve-by time stays one per box.

## Where is the source written and shown?
- `ws_mail_ingest()` option `source` / `source_id`, written only for a conversation the mail opened (`ws_mail_source_set()`): a support form passes form and the record id, the chat window's request chat and the chat id, My Account's Reply account (only when the answer did not thread back). WhatsApp and SMS write their transport in `ws_mail_phone_ingest()`.
- List row: a plain label Form, Live chat or My Account (the row is a link, so no link inside). Reading pane head: "Opened from" and the label; Form opens `edit_submitted_form.php?id=`, Live chat opens the chat in the panel's chat window (`data-ws-mail-source-chat`, `pgChatOpenConversation()`). Mail, WhatsApp and SMS draw none (the transport badge says the last two).

## Tests?
- `tests/workspace_mail_priority_test.php`; the box form's names in `tests/workspace_mail_drawer_test.php`. The step, the save, the list and the pane against a database and in a browser were not run in the cloud session (PR #12218).

Related: docs/memory/workspace/mail-list.md, docs/memory/workspace/mail-status.md, docs/memory/workspace/mail-resolve-by.md, docs/memory/workspace/support-form-request.md
