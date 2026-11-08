---
name: agent-canvas-edit
description: An agent edits any canvas of its channel in place (POST|PATCH /workspace/messages/{id}/canvas): the operations, 409 on a stale version, the agent version, the author's inbox line
---

## What can an edit do?
- `ws_canvas_edit_apply($doc, $operations)` (`includes/workspace/canvas_edit.php`, pure but for the sanitizer) applies at most 20 operations in order, all or none, to the newest version's document: `add` (svg; box optional: its own size made to fit the artboard and centred, `ws_canvas_fit_box()`), `replace` (an svg layer's drawing, its box kept, its view box the new root's), `remove`, `text` (a text layer's words, `color` #rrggbb), `box` (any of x, y, w, h, rot; numbers only, w and h above 0, nothing beyond 100000), `svg` (the whole canvas anew through `ws_canvas_from_svg()`, the background kept unless given).
- New ids stay clear of the ids already in the document (`ws_canvas_taken_ids()`, the editor's `takenIds()`; a replaced layer's own ids are free again), so a second import gets `i2-`. A new layer id is the editor's `newId()`.
- A locked layer is refused; so is text on a non-text layer or SVG on a non-svg layer. A refusal is 422 on `operations`, its message starting "Operation n:" (from 0).

## How is it kept?
- The route (`workspace.messages.canvas.edit`, `api_canvas.php`): POST with `also_accepts` PATCH (IIS). `version` must be the newest `version_no` (`GET …/canvas` gives it), else 409 `conflict`. Who: as for create (`ws_api_canvas_agent()`), on any canvas of a channel the owner may post in, an agent's or a person's.
- The edited document, `picture: pending` and `by` the agent, is a new `file_versions` row with source `agent` over the same pixels (the file keeps its last drawn picture until a browser draws the new one), then `pg_file_versions_prune()` keeps the original and the last ten.
- A canvas whose message a person wrote: its author gets the inbox line `canvas_edited` ("@handle edited your canvas in #channel", `ws_canvas_inbox_describe()`, actor 0), unless the owner of the application is that author.

## What is not done yet?
- The "Edited by @handle" mark on the message and its Undo (the version before as a new one): plan step C3b, in the channel's script.
