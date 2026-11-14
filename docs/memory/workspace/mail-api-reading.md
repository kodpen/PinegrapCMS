---
name: mail-api-reading
description: A chat reading Workspace e-mail over the API and MCP (MT2): one mail whole, its cleaned HTML on request, its files listed and fetched as JSON, the outside mark, text_max
---

## Which routes read a mail? (`includes/workspace/api_mail.php`)
- `workspace.mail_items.get` GET /workspace/mail/items/{id} (tool): one mail whole, reached through its conversation's channel (`ws_api_mail_channel_reached()`, 404 otherwise). A delivery failure (no conversation) is reached through the answer it returns (`ws_api_mail_item_conversation_id()`, by `in_reply_to`).
- `include_html` true adds `html`: `body_html` as kept (`mail-html.md`), outside pictures still in `data-pg-src`, own pictures naming `data-pg-file`; null without it, before `ws_mail_html_ready()` or when the mail has none.
- `workspace.mail_conversations.get` takes `text_max` (0, the default, keeps each text whole); `text_cut` says a text was cut, in characters (`ws_api_mail_text_cut()`, pure).

## What does every mail carry now? (`ws_api_mail_present()`)
- `conversation_id`, `outside` (`ws_message_outside('mail', direction)`: true for mail that came in), `has_html`, `html` (null in a conversation), `bcc` (an out mail's only; null for incoming), `files` [{message_id, name, size, content_type}] (`WorkspaceMailFile`).
- The files are `ws_messages` rows with `sender_kind` mail, `sender_id` the mail, `file_id` > 0, not deleted (`ws_api_mail_files_map()`, grouped by the pure `ws_api_mail_files_group()`).
- An out mail's `text` is what its writer typed: name, Signature and quote are added when it leaves.

## How does a chat fetch a mail's file?
- `workspace.files.content` GET /workspace/messages/{id}/file/content (tool, `includes/workspace/api_resources.php`): {message_id, name, content_type, size, content_base64}, at most `WS_API_FILE_CONTENT_MAX` (1 MB); larger answers 422 naming `id`, and the binary `workspace.files.get` gives its bytes.
- Both routes share `ws_api_message_file_or_404()`: `ws_can_read_channel()`, the Claude application's limits (`ws_api_channel_or_404()`), a file not deleted.

## Tests?
- `tests/workspace_api_mail_test.php` (reading fields, files grouping, item reach, file content). No route was called against a database (MT2).
