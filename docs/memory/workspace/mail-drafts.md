---
name: mail-drafts
description: E-mail drafts kept on the server (MT3a #9516, MT3b): the ws_mail_items row, who reads and changes one, the routes and tools, the readers that leave drafts out, the E-mails screen's Drafts view
---

## Where is a draft kept?
- A `ws_mail_items` row: `direction` out, `send_state` `draft` (`WS_MAIL_DRAFT_STATE`), `message_id` 0, `user_id` the writer (`api_current_app()['owner']` through the API), `created_at` moved on every change. No column of its own (`includes/workspace/mail_drafts.php`).
- `rfc_message_hash` is a random hash (`ws_mail_draft_hash()`): the table is unique on (box_id, rfc_message_hash), so two drafts may never share ''.
- A reply's draft has the conversation's id, its To and subject the conversation's (`ws_mail_draft_merge()` keeps them). A new mail's has `conversation_id` 0 and a box from `ws_mail_send_boxes()`; "Kept in" is chosen when it is sent.

## Who reads and changes one?
- A reply's: whoever reads the conversation's channel reads it; whoever may answer there (`ws_mail_can_answer()`) writes, changes and deletes it. A new mail's: its writer alone (`ws_mail_draft_reached()`).
- The API adds the Claude application's narrowing (`ws_api_mail_draft_reach()`). Every caller kind may keep drafts; only a person's connection sends one.
- At most `WS_MAIL_DRAFTS_MAX` (200) per writer; a draft may be unfinished, never empty (`ws_mail_draft_has_words()`); addresses are kept as typed and checked at Send.

## Which routes?
- `includes/workspace/api_mail_drafts.php`: `workspace.mail_drafts.list|get|create|update|delete` (tools) and `delete_post` (no tool); `send` is the person's (`mail-send-scope.md`), object `WorkspaceMailDraft`; every write has `dry_run`. Refusals `ws_api_mail_draft_refusal()`: 404 conversation, 403 no_access, 503 before 11.0.185, 422 the rest.

## Which readers leave drafts out?
- `ws_mail_drafts_excluded_sql()` ('1 = 1' before the send state is kept): the pane (`ws_mail_pane()`), the API's conversation, one mail and read mark, the E-mails list's search (`ws_mail_list_where()`) and its count and snippet (`ws_mail_list_mails()`), the card's "Answered · who" (`ws_mail_items_map()`), who took part when a mail is threaded (`ws_mail_find_conversation()`), the recipient suggestions (`ws_mail_suggest_heard()`, which also leaves blocked mail out).
- `ws_mail_send_state()` gives `draft`, never `sent`; `ws_mail_send_last_hour()` does not count drafts against the hourly limit. `tests/workspace_mail_drafts_test.php` names each.

## What does the E-mails screen show? (MT3b)
- "Drafts" (Turkish "Taslaklar"), a view of the toolbar (`view=drafts`) offered with New e-mail: the drafts the person reads, newest change first, at most 100, as rows of the screen's own list (`.ws-mail-rows`), the mailbox on the meta line (`includes/workspace/mail_drafts_screen.php`). Status, holder, kind and the first-answer filter are left out, as in Blocked.
- "Open draft" (action `ws_mail_draft_open`, only for whoever may change it) fills the Reply by e-mail drawer, with the conversation open in the pane, or the New e-mail form; the browser's kept form is neither read nor written for it. Delete (`ws_mail_draft_remove`) removes it unsent, asked first with `pgConfirm()`. An opened draft's form has "Save draft" beside Send (`ws_mail_draft_save`, `ws_mail_draft_save()`): a change is never lost by closing the form (architect's look, 3362).
- Send posts `draft_id`; once the mail is stored the draft goes (`ws_mail_draft_remove()` with the sent mail's conversation: a reply's draft only with an answer in its conversation, a new mail's only with a new mail). A mail that is stored but fails to leave still deletes its draft: it waits under Not sent.
- Tests: `tests/workspace_mail_drafts_screen_test.php`.

Related: docs/memory/plans/mail-chat-tools-drafts.md, docs/memory/workspace/mail-api.md
