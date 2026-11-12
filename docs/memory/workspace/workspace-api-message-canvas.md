---
name: workspace-api-message-canvas
description: file.canvas of an API message (#8649): a canvas picture's newest version and pending, server or drawn, read once a page; which writes touch the message so changed_since and sync bring it
---

## What does an API client see in the message?
- `WorkspaceMessageFile.canvas` {version, picture}, null on any other file: `ws_api_message_canvas()` in `includes/workspace/api_resources.php`, schema `WorkspaceMessageCanvas`.
- `version` is the newest `file_versions` row's number; `picture` is pending (a blank or the previous picture, a browser has to draw it), server (the server drew it, CV6; a member's browser that shows it draws it again and the picture changes once more) or drawn.
- A device keeps its copy of the picture while version and picture stay the same. `url` and `api_url` stay as they were, with no `?v=`.

## Why not the words of GET messages/{id}/canvas?
- That read says drawn for a server picture too. The file's `picture` keeps server apart because the bytes still change when a browser redraws it, and a copy kept by version alone would hold the server's picture.

## Which files count, and how is it read?
- A PNG of a message not deleted (`ws_api_message_canvas_file_ids()`), as the panel's conversation asks; a picture whose newest version keeps no document is no canvas.
- `ws_canvas_message_states()` (`includes/workspace/canvas.php`): one query for a page, the newest row per file through `MAX(version_no)` on `idx_file_version`, slices of 500. Its rule is the panel's (`ws_canvas_version_state()`, shared with `ws_canvas_message_state()`).
- `ws_api_messages_present()` passes the map to `ws_api_message_present()` (its last argument); a message presented alone reads its own.

## When does the message come again?
- A browser's take, Undo, the server's drawing (`ws_canvas_draw_now()`, only when it draws), Pinegrap AI's edit and `POST messages/{id}/canvas` touch it (`ws_message_touch()`); the API edit did not before #8649. `changed_since` on the channel's messages and `changed` of `/workspace/sync` then bring it with the new `file.canvas`.

## Tests?
- `tests/workspace_api_message_canvas_test.php`: the state rule, which files, the object, the schema, the one query, the edit's touch, the route text.
