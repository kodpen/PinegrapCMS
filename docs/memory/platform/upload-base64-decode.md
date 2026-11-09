---
name: upload-base64-decode
description: Base64 uploads (panel upload_file, chat attachments) decode with pg_upload_base64_decode(), cut at the first "base64,", strict; one whose text held "base64" was stored cut short (#8385)
module: files
---

## What broke?
- The panel's `upload_file` action (`pg_panel_upload_file()`, `includes/panel/content.php`) and chat attachments (`pg_chat_store_upload()`, `chat.php`) split the posted text at every `base64` and decoded only what followed the last one. Base64 text can hold those letters itself, so such a file was stored with only its tail (empty when the letters ended the text), and no error was shown.
- Rare, but silent: one six-letter window in 64^6, about one file in 10,000 at 5 MB. Seen in the code on 2026-11-09; no user reported it, and a file it cut cannot be rebuilt, so nothing stored is changed.

## What decodes such a file now?
- `pg_upload_base64_decode($data)` in `includes/fn/files.php` (#8385): the text after the first `base64,` (the whole text when there is no `data:` prefix), a space read as `+` (a body posted without encoding turns `+` into a space), then `base64_decode(…, true)`.
- Line breaks are skipped. Anything else outside the alphabet (`%`, `-`/`_`, a comma, a `data:` address that is not base64, a value that is not a string) returns `false`; an empty body returns `''`.
- Same rule as Workspace's `ws_upload_decode()` (`includes/workspace/files.php`).

## What does a door do with false?
- `upload_file` answers `The file could not be read.` in JSON and writes nothing. Before, the lenient decode wrote whatever bytes it made of the text: a bare `data:` became three bytes.
- Chat refuses false or an empty result with `This file type is not allowed.`, as it refused an empty body before.

## Which doors decode base64 another way?
- File Manager uploads: `pg_explorer_write_base64()` (`view_folder_and_files_f.php`) cuts at the first comma and decodes in 64 KB slices.
- The image editor, Edit File, contact photos and signatures match a fixed `data:image/...;base64,` prefix.

## How is it tested?
- `tests/upload_base64_decode_test.php`: texts holding `base64` at the start, in the middle and at the end, a browser data URL, spaces for `+`, line breaks, an empty body, the refusals, and a guard that both doors call the helper. Before the change it failed on the missing function and on the two `explode('base64', …)` splits.

Related: docs/memory/platform/upload-refusal-answers.md, docs/memory/platform/chat-attachment-access.md
