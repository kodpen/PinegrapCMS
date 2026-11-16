---
name: poll-question-picture
description: A poll question's own picture (poll_picture.php, #9948): carried by the question's message, the image check, ask again carries it, change while unanswered, the API routes, ws_poll.php --file
---

## What does a question carry?
- One picture of its own, sent with the question in one call (#9948). The question is a message and its message carries the picture as its file (`ws_messages.file_id`, `file_name`): whoever reads the message reads it, and the channel's Files tab lists it as that message's file. No schema change.
- Owner (2026-11-16, `workflow/decisions-made-workspace-36.md`): the picture stands in the question's own box. The box is QP2 of `plans/agent-question-picture.md`; until then the panel draws it as the file card under the poll box.

## Which file passes?
- Pure `ws_poll_picture_refusal($name, $size, $max)`: an image (`ws_file_kind()` image: JPG, PNG, GIF, WebP, AVIF, BMP), not empty, at most `ws_upload_max_bytes()`; else a sentence. Then `ws_upload_check()` (the channel upload's checks). `ws_poll_picture_check()` does both, keeping nothing.

## How is it kept?
- `ws_poll_picture_send()`: the message is checked first (`ws_message_send_check()` with the file standing in), then the file kept (`ws_store_upload_bytes()`), then the message written; a message refused after all takes the file back (`ws_upload_discard()`). `ws_poll_create()` checks the picture before sending (`$data['picture']`: name and bytes).

## Ask again, take back, change?
- Ask again carries the old question's picture (`ws_poll_picture_of_message()`, an image file only) as the same kept file, unless a new one is sent; `picture` null (API `remove_picture`) leaves it out (pure `ws_poll_again_data()`).
- Take back changes no file: the greyed question keeps it.
- `ws_poll_picture_change()`: the asker replaces or removes it while the poll is open, not taken back and unanswered (pure `ws_poll_picture_change_refusal()`); the old file stays in the channel's folder, as a deleted message's does.

## Over the API?
- `POST /workspace/channels/{id}/polls`, `POST /workspace/polls/{id}` and `…/again` take it as `workspace.files.create` takes a file (`form_file` file, or `content_base64` with `name`; `ws_api_poll_picture_input()` over `ws_api_upload_input()`). update and again also take `remove_picture`; both together are refused (422). A refusal names the parameter it came in.
- A dry run checks it and stores nothing; `detail.file` gives name, kind, size. The answer's message carries `file` as any message does.

## The tool?
- `php tools/ws_poll.php ask --file=PATH …` (and `again`) sends `name` and `content_base64` (`ws_poll_file_read()`, `ws_poll_ask_body()` flag `file`); a missing or unreadable file stops with exit 1 before any call.

## Tested?
- `tests/workspace_poll_picture_test.php` (red first). No live call against a database; the writing paths are pinned by source tests.
