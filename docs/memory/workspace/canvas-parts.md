---
name: canvas-parts
description: A canvas's second part (ws_canvas_parts, 11.0.194, #9258): the message's own file is the first part, a row names the other; who reads it as the part, adding one over the API, what the card shows yet
---

## What is stored?
- `ws_canvas_parts` (2026.11.0 step 11.0.194, owner answer S1 #8923): `message_id` (the canvas), `part` `drawing` | `page`, `file_id`, `made_by_kind` `user` | `app`, `made_by_id`, `created_at`; `UNIQUE (message_id, part)`, `idx_file`. No foreign keys. Listed in `get_tables()`.
- The message's own file is the first part and never gets a row; a row for the kind the message's own file is counts for nothing (`ws_canvas_part_file_pick()`, pure).
- `includes/workspace/canvas_parts.php`; `ws_canvas_parts_ready()` probes `made_by_kind`.

## How is a part read?
- `ws_canvas_part_file($message, $part)` gives the part's file (0 for none); `ws_canvas_part_message($message, $part)` gives the message row with that file as its `file_id`, which the part's existing readers and writers take unchanged (`ws_api_canvas_present()`, `ws_page_write()`, `ws_api_canvas_new_version()`, `ws_canvas_draw_now()`).
- The page part decides the data side: `ws_canvas_page_reach()` (bindings), `ws_page_bridge_answer()` and `ws_canvas_page_version()` (the first-write question) read the page part, so a page added beside a drawing binds tables and asks for writes as a message's own page does.

## How is a part added?
- Over the API only: `POST /workspace/canvases/{id}/page` or `…/drawing` with `version` 0 (`api/workspace-canvas-api.md`). The file is stored in the channel's folder (`ws_store_file_bytes()`, an `.html` page or a blank `.png` keeping the canvas document), its first version kept as the agent's, then the row; the message is not changed, only touched.
- Two adds at once keep one row (`INSERT IGNORE` on the unique key): the loser deletes its file and versions and answers 409 `conflict`.

## What does the panel show yet?
- Architect (2026-11-13, ch.25 #1931): until the joined card (CO7c), a canvas with two parts shows its first part only on its card; accepted as the state between the steps.

## What removes a part?
- A thread's purge deletes the rows (`threads.php`); the part files stay in the channel's folder, as a thread's other files do. A deleted message's row stays and reaches nothing, as the bindings do.

## Tests?
- `tests/workspace_canvas_api_test.php`: the pick, the step and its label, version 0 on the routes. On a sandbox database (#9258) the step ran three times clean, and a page added beside a drawing and a drawing beside a page were read, written, presented and bound to a table.
