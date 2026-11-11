---
name: mail-api
description: Workspace e-mail in the external API and MCP (api_mail.php, G7, WM8 #8737): routes, list filters, unread, send_state, which are tools, who may answer or send, errors, the webhook
---

## Which routes?
- `includes/workspace/api_mail.php`, `ws_mail_api_routes()` merged at the end of `ws_api_routes()`. Scopes `workspace:read` / `workspace:write`, no new scope; no mailbox management in the API.
- `GET /workspace/mail/conversations`: status, holder me/none, channel_id; cursor on newest mail + id, limit 1-100.
- Its view, box_id, q and unread go through the screen's own `ws_mail_list_filters()`, `ws_mail_list_where()` and `ws_mail_unread_sql()` (via `ws_api_mail_list_filters()`); view is `all` by default, so a caller sending none gets what it always got.
- `GET …/{id}` (with `mails`, newest 200 oldest first, plus the delivery failures of its answers found by `in_reply_to`), `POST …/{id}` (+PATCH: status, assignee_id; 0 or null = nobody), `POST …/{id}/reply` (text, 201 `WorkspaceMail` with `copy_kept`).
- `POST …/{id}/read` (marks it read up to its newest mail, `ws_mail_read_mark()`), `POST /workspace/mail/messages` (a new mail, 201 `WorkspaceMailConversation` with its mail).
- Objects `WorkspaceMailConversation` (overdue, first_reply_due_at from the box's target, `unread` for the owner by the screen's rule, null before 11.0.185; GET does not mark read) and `WorkspaceMail` (direction, verified only for incoming, automatic, bounce, refused_files, to, cc, `send_state` sent/queued/failed with `send_error`, null for incoming).
- A channel decides, not the box: `ws_api_mail_channel_reached()` = `ws_can_read_channel()`, and the Claude application only where `ws_claude_channel_allowed()`.

## Which are tools, and who may answer?
- List, get and the status/holder change are MCP tools. The change runs `ws_mail_conversation_check()` (split from `ws_mail_conversation_set()` for the dry run); nothing to change answers 200 with the conversation.
- The reply is no tool (`'mcp' => false`) and needs the team devices session (`ws_api_own_words_only()`): an assistant never writes to a customer (design rule), and a key writes as an application. Same checks as the panel (`ws_mail_reply_check()`), then `api_dry_run_stop()`, then `ws_mail_reply_send()`.
- The read mark is a tool held to `ws_api_person_only()` like `workspace.inbox.read`: a device session or an OAuth connection, never a key (an integration reading is not the person reading).
- A new mail is no tool (`'mcp' => false`), person-only (`ws_api_own_words_only()`; plan rule: assistants and applications never send, a draft is theirs, Send the person's). Same path as the panel's New e-mail: `ws_mail_compose_mail()` (no files yet), `ws_mail_send_check()`, `api_dry_run_stop()`, `ws_mail_send()`; `channel_id` left out is the box's own. Refusals `ws_api_mail_send_refusal()`: 422 to/subject/text, 403 box_id/channel_id, 429 hourly, 409 not_sent.
- Reply errors: 422 `text`; 403 when the person may not write in the channel; 409 `cannot_send` (box off, no sending server, domain, no address) and 409 `not_sent` (server refused, its words appended). A mail that left but whose row was lost still answers 201 with id 0, so an Idempotency-Key retry does not mail twice.

## What does a webhook carry?
- `workspace.mail.received` (`ws_webhook_events()`), from `ws_mail_ingest()` for every stored incoming mail, any channel kind: ids only (conversation_id, channel_id, message_id, box_id, new, verified), never address, subject or text; the reader still needs the channel to GET it.

## How is a mail message shown in message lists?
- `ws_api_message_present()`: `sender_kind` mail gets `sender` from `ws_mail_sender()` (user_id for a team answer) and `mail` {item_id, conversation_id, direction, from_email} (`WorkspaceMessageMail`); null for other kinds.

## Tests?
- `tests/workspace_api_mail_test.php`. No route was called against a database or a mail server in the cloud sessions (G7, #8737).
