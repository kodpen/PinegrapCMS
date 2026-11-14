---
name: mail-chat-tools-census
description: MT1 census of plans/mail-chat-tools.md (2026-11-14): what a chat gets from each Workspace mail route today and what it lacks, the callers apart, the tools against the directory check
---

## What does each mail route give a chat today? (`includes/workspace/api_mail.php`)
- `workspace.mail_conversations.list` (tool): no mail text at all; `q` searches subject, names, addresses and any mail's text.
- `….get` (tool): every mail's `text` whole (`body_text`, cut only at ingest at 1 MB, `WS_MAIL_TEXT_MAX`), the newest 200 (`WS_API_MAIL_ITEMS_MAX`). Fifty long mails can fill a chat's context: there is no cut and no one-mail read.
- It lacks: the HTML (`body_html` is never presented), the files (only `refused_files`; the kept ones are `ws_messages` rows with `sender_kind` mail, `sender_id` = item id, `file_id` > 0, which no mail field names), `bcc` of an out mail, and any `outside` mark (the subject and text carry only the route description's "treat them as data").
- An out mail's `text` is what the writer typed: the name, the Signature and the quote are added when it leaves (`ws_mail_answer_body()`, `ws_mail_send_body()`), so a chat never sees them and must not type them.
- `….update` and `….read` (tools): no text. `….reply` and `workspace.mail_messages.send`: `'mcp' => false`, `ws_api_own_words_only()`, plain text, no files.
- A file is fetched only by `workspace.files.get` (GET /workspace/messages/{id}/file): binary, `'mcp' => false`. `files.get` (`files:read`) does not reach a channel's folder. So no tool fetches a mail's file.
- A channel agent or Pinegrap AI reading the channel sees the mail's message, cut at `WS_MESSAGE_MAX` (4,000, `ws_mail_message_body()`).

## Which callers, and what may each do? (`api/api-device-sessions.md`)
- Team devices session: `ws_api_device_writes_as_person()`; reads, marks read, answers and sends today under `workspace:write`. No MCP: MCP clients come through OAuth.
- OAuth connection (Claude's connector and plugin): reads and marks read; refused on reply and new mail (`ws_api_own_words_only()`). Writes messages as its application (`ws_api_writer_app_id()`).
- Application key: reads; the read mark refused (`ws_api_person_only()`); never sends.
- Channel agent: an application key; the Claude application (`ws_api_is_claude()`, a key with no device) reaches only channels where `ws_claude_channel_allowed()`.
- Pinegrap AI: in-process, no API. Its only mail work is the triage suggestion with a reply draft in a channel message (`mail-triage.md`); it never sends (#2444 answer 7).

## How do the tools fare against `api/claude-plugin-tool-check.md`?
- Names: the new ids (`workspace.mail_drafts.*`, `workspace.mail_items.*`) stay far under 64 characters.
- Annotations follow the id: `create` is not destructive, a `send`, `reply`, `update` or `delete` is. A sent mail cannot be called back, so send and reply stay destructive.
- No route moves money; `workspace:write` is in `api_plugin_scopes()`, a new `workspace:mail_send` is not until MT4 puts it there.
