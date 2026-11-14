---
name: mail-api-html-files
description: HTML and files in Workspace e-mail sent or drafted over the API and MCP (MT5, #9541): html, file_ids, file right at Send, draft_files, draft_app_id
---

## Which routes take HTML and files?
- `workspace.mail_conversations.reply`, `workspace.mail_messages.send`, `workspace.mail_drafts.create` and `.update` take `html` (string, up to 1 MB) and `file_ids` (list of `files.id`, at most `WS_MAIL_SEND_FILES`). `text` is no longer required: left out with `html`, it is made from the HTML's words (`ws_mail_text_or_html()`, `ws_mail_html_text()` of the cleaned HTML).
- `html` is cleaned by `pg_mail_html_clean()` (the incoming cleaner) and kept in `body_html`; it leaves as MA10b2 sends a written mail's HTML (`mail-html-written.md`). HTML the cleaner refuses: 422.
- A reply with `html` or `file_ids` goes through `ws_mail_send()` with the conversation, as the card's drawer sends one (`ws_api_mail_reply_queued()`): queued, `copy_kept` null. A plain-text reply keeps the direct path. A WhatsApp conversation refuses both with 422.

## How does a chat find a file's id?
- `file.id` of a message (`WorkspaceMessageFile`) and `file_id` of a mail's file (`WorkspaceMailFile`) give the Files id. A new file is posted first with `workspace.files.create` (1 MB as a tool).

## Which files may a person send?
- `ws_mail_send_file_reach()` (`mail_compose.php`): a file the file manager lets them bring into a conversation (`ws_library_file()`), or the file of a message not deleted in a channel they read (the file manager does not reach a channel's folder); the Claude application only in its channels; a withheld file or an ERP document never.
- `ws_mail_send_files_by_id()` adds the sizes up before any file is read: 422 `file_ids` for too many, over `WS_MAIL_SEND_FILES_BYTES`, or a file not reached (`ws_api_mail_send_refusal()`).

## Where does a draft keep them?
- HTML in `body_html`, files in `ws_mail_items.draft_files` TEXT NULL, a comma list of `files.id` (`ws_mail_draft_keep_body()`, written only once each column is there). Owner's yes: `workflow/decisions-made-schema-18.md`.
- Step `upgrade_2026_11_0_ws_mail_draft_files()` (11.0.205), `install_add_column()`; run twice in `tests/workspace_mail_draft_files_schema_test.php` (fake db; no dev database in the session).
- `ws_mail_items.draft_app_id` INT UNSIGNED NOT NULL DEFAULT 0, same step, no backfill: the `ws_api_writer_app_id()` of the caller that created the draft (0 for the panel, a person's device session or OAuth connection), written on create only (`ws_mail_draft_keep_app()`); a later change by anyone leaves it. Owner's yes: channel poll 3369, result 3372; shape by the architect (3377).
- A draft's `file_ids` replaces its list (an empty list takes them off); the writer's right to each is checked when it is kept and again for the sender at Send (`ws_api_mail_drafts_send()`); the sent draft's row goes, its list with it.
- `WorkspaceMailDraft` gains `html` (null for none) and `file_ids`. A file or HTML alone is a draft (`ws_mail_draft_has_words()`).

## What does the Drafts view do with them? (`mail_drafts_screen.php`)
- Open draft: the text (its HTML's words when it has none), its files in the form's Files list (`ws_mail_draft_form_files()`, only those the person may send), and a note under the text when it keeps HTML the form does not show.
- Send: the draft's `body_html` while the text is unchanged (`ws_mail_draft_sent_mail()`), else the form's; its files go by `file_ids`, and `ws_mail_compose_library_files()` takes a channel message's file too (`ws_mail_send_file_reach()`).
- Save draft: an unchanged text keeps text and HTML; a changed one takes the writing box's HTML or none (`ws_mail_draft_save_request()`); the Files list replaces `draft_files`. PR Merge held MT5 for this loss (channel 3407).

## Tests?
- `tests/workspace_mail_html_files_out_test.php`, `workspace_mail_drafts_test.php`, `workspace_api_mail_test.php`. No route was called against a database or a mail server.
