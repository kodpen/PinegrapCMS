---
name: mail-chat-tools-drafts
description: MT3's exact scope (mail-chat-tools plan): where a draft lives in ws_mail_items, who reads and changes one, routes and tools, readers to guard, the Drafts view
---

## Where does a draft live? (no column)
- A `ws_mail_items` row: `direction` out, `send_state` `draft` (VARCHAR(8)), `box_id` set, `message_id` 0, `user_id` the caller's owner (`api_current_app()['owner']`, never 0: an out row with `user_id` 0 is an automatic answer), `created_at` moved on every change.
- `rfc_message_hash` is unique per box (`uq_box_message`): a draft takes the hash of a random id of its own, never ''.
- A reply's draft: `conversation_id` the conversation, To the customer as the answer takes it. A new mail's draft: `conversation_id` 0; "Kept in" has no column and is chosen when it is sent (the box's own channel by default).

## Who reads and changes one?
- A reply's draft: whoever reads the conversation (`ws_api_mail_conversation_or_404()`); it is written, changed and deleted by whoever may answer there (`ws_mail_reply_check()` without sending).
- A new mail's draft: its writer (`user_id`) only, from a box `ws_mail_send_boxes()` gives them.
- Every caller kind may: device session, OAuth connection, application key, channel agent (the Claude application within `ws_claude_channel_allowed()`). Sending one is MT4's and a person's.
- At most 200 drafts per writer (422 beyond); text up to `WS_MAIL_REPLY_MAX`, To/Cc/Bcc as the send check takes them, checked again when sent.

## Which routes and tools?
- `workspace.mail_drafts.list` GET /workspace/mail/drafts (conversation_id, box_id, kind reply|new, cursor), `….get` GET …/{id}, `….create` POST /workspace/mail/drafts (conversation_id, or box_id with to, cc, bcc, subject; text), `….update` POST …/{id} (+PATCH), `….delete` DELETE …/{id} with `….delete_post` POST …/{id}/delete. All tools, all `dry_run`.
- Object `WorkspaceMailDraft`: id, kind, conversation_id, box_id, to, cc, bcc, subject, text, user_id, updated_at. The tool descriptions say a draft is never sent by itself.

## Which readers must leave a draft out?
- `ws_mail_send_state()` (any state but queued and failed reads `sent`), `ws_mail_send_last_hour()` (the hourly limit counts every out row of the person), the pane's items (`mail_pane.php`), `ws_api_mail_conversation_answer()`, the list's `q` (searches every item's text). A test lays each against a draft row.
- Already safe: the Sent and Not sent views (`send_state IN …`), bounce matching (`direction` in), the queue (`send_state = 'queued'`).

## What does the E-mails screen get?
- A Drafts view beside Incoming, Sent and Not sent: the drafts the person reads, newest change first. A reply's draft opens the conversation's Reply by e-mail drawer filled, a new mail's the New e-mail form; Send there is the person's and deletes the draft once the mail is stored. The browser's kept form (`mail-compose.md`) stays. Look A, pictures in the channel.
