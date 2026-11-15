---
name: workspace-message-file-api
description: The API routes for files in a message: post a file of the file manager, put an edited file in place of the old one (own words, no tool), list a file's earlier versions
---

## Which routes, and which panel function does each run?
- `POST /workspace/channels/{id}/library-files` (`workspace.channels.library_files.create`, tool): `ws_library_send()` (`includes/workspace/library.php`), as From the file manager under the paperclip. Body `file_id`, `text`. Written as `POST …/files` is: app key as the application, OAuth via its client, device as the person.
- `POST /workspace/messages/{id}/file` (`workspace.messages.file.replace`, `'mcp' => false`): `ws_message_file_replace()` (`includes/workspace/file_edits.php`), as saving over one's file in the image, text and office editors. File as form part `file` or `content_base64`; `name` only for a new extension.
- `GET /workspace/messages/{id}/file/history` (`workspace.messages.file.history`, tool): `ws_file_history()`, newest first, at most 50, with the redaction rule of `ws_file_edits_visible()`.

## Where are the checks the dry run meets?
- Library: `ws_library_send_check()` (the file the person may bring in, size, `ws_upload_check()`, `ws_message_send_check()`); `ws_library_send()` calls it, so the panel and the API share it. Field `file_id` or `text`.
- Replace: `ws_file_edit_target()` (file still there, an editor opens it), `ws_file_can_replace()` (403: own message, not locked, may post), `ws_message_file_replace_check()` (shared with `ws_message_file_replace()`), then `ws_upload_check()` on the bytes; a text file at most `WS_FILE_TEXT_MAX`.

## Who may replace a file?
- Only the person's own team devices session (`ws_api_own_words_only()`), in a message they wrote: the panel rule `ws_file_can_replace()` needs `sender_kind = user`, so an agent's or application's message is not replaced. Somebody else's file is answered with `POST …/files` and `parent_id` (the panel's edited copy).
- The edited file keeps the old name with its own extension (`ws_message_file_replace_name()`): same kind (`ws_file_edit_kind()`: image, office, text), a document stays a document and a workbook a workbook; else 422 on `name`.
- No `redacted` flag over the API: the earlier version of a replacement stays visible to every reader.

## What does a history entry hold?
- `name`, `url` (the site's own address of the kept file, opens only signed in; '' when gone), `edited_by` {user_id, name}, `created_at`. `ws_file_history()` also returns `by_id`, `created_at` and `stored` for it; the panel ignores them.
- An earlier era's message is read too (the panel's reach); only writes refuse it.

## Who asks the assistants?
- `ws_message_send_file_bytes()` asks them itself only when neither `app_id` nor `via_client_id` is set; an application's or connection's words wake only the registry's agents, from its route (`ws_agent_after_app_send()`).
