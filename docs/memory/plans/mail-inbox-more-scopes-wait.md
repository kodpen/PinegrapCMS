---
name: mail-inbox-more-scopes-wait
description: Exact scope of IB10, IB10b and IB13 of plans/mail-inbox-more.md after the IB9 census: remind later, remind when no answer comes, undo send; files, functions, texts, schema asked
---

## IB10: Remind later?
- Where: the reading pane's tools (`ws_mail_pane_tools()`, mail_pane.php; the list rows have no menu), a "Remind me later" menu for whoever reads the conversation: "In an hour", "Tomorrow morning" (09:00), "Next week" (Monday 09:00), "Choose a time", in `user.timezone`; while set, "Cancel reminder". Panel action `ws_mail_remind` (set or cancel); new module `includes/workspace/mail_remind.php`.
- Hidden: `ws_mail_list_where()` and `ws_mail_unread_count()` leave out the viewer's waiting conversations; nobody else's list changes.
- Wake: `ws_mail_reminders_run()` hooked into `ws_task_reminders_waiting()` / `_run()` as personal plans are; at the time, or at once when `ws_mail_ingest()` stores a new incoming mail in it. Inbox kind `mail_remind` ("Reminder: {subject}", `ws_mail_inbox_describe()`); a woken conversation reads unread for that person (`ws_mail_unread_sql()`) until they open it.
- No API route or MCP tool.

## IB10b: Remind when no answer comes?
- Where: "Remind me if no answer comes" (No, 1 day, 3 days, 7 days) on Reply by e-mail (`replyForm()`) and New e-mail (`ws_mail_compose`), passed to `ws_mail_send()` / `ws_mail_send_answer()` as `remind_days`; a mail sent later counts from its sending.
- An incoming mail in the conversation cancels it (`ws_mail_ingest()`). Due without one: inbox kind `mail_no_answer` ("No answer yet: {subject}") to the writer, and "No answer yet" on the writer's list row and in the pane until an answer comes. Same run and module as IB10.

## Which schema do IB10 and IB10b need? (asked of the architect)
- A, recommended: table `ws_mail_reminders` (id, conversation_id, user_id, kind VARCHAR(10) later | no_answer, item_id, due_at, woken_at, created_at; UNIQUE (conversation_id, user_id, kind); KEY idx_due (woken_at, due_at)). One table, one run, nothing added to `ws_mail_items`, which grows with every mail.
- B: `ws_mail_reads.remind_at` and `woken_at` for IB10, `ws_mail_items.no_answer_at` with an index for IB10b.

## IB13: Undo send?
- After IB6: the panel's Send (New e-mail, Reply by e-mail, Forward as e-mail) keeps the mail as IB6's waiting mail, due in 10 seconds, with a line "Sending in 10 seconds" and "Undo". After the wait the browser asks for that one mail to leave (an action beside IB6's "Send now"); the job is the fallback when the page closed.
- "Undo" (action `ws_mail_undo_send`) turns the item into a draft (`send_state` `draft`, `mail-drafts.md`) and opens its form again; a new mail's card and conversation follow what IB6 writes for a waiting mail. The API sends at once as today (no setting).
- Schema: none beyond IB6's.
