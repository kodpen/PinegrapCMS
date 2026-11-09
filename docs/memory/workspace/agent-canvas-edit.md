---
name: agent-canvas-edit
description: An agent edits any canvas of its channel in place (POST|PATCH messages/{id}/canvas): operations, 409 when stale, the agent version, the inbox line, the "Edited by @handle" mark and Undo
---

## What can an edit do?
- `ws_canvas_edit_apply($doc, $operations)` (`includes/workspace/canvas_edit.php`, pure but for the sanitizer) applies at most 20 operations in order, all or none, to the newest version's document: `add` (svg; box optional: its own size made to fit the artboard and centred, `ws_canvas_fit_box()`), `replace` (an svg layer's drawing, its box kept, its view box the new root's), `remove`, `text` (a text layer's words, `color` #rrggbb), `box` (any of x, y, w, h, rot; numbers only, w and h above 0, nothing beyond 100000), `svg` (the whole canvas anew through `ws_canvas_from_svg()`, the background kept unless given).
- New ids stay clear of the ids already in the document (`ws_canvas_taken_ids()`, the editor's `takenIds()`; a replaced layer's own ids are free again), so a second import gets `i2-`. A new layer id is the editor's `newId()`.
- A locked layer is refused; so is text on a non-text layer or SVG on a non-svg layer. A refusal is 422 on `operations`, its message starting "Operation n:" (from 0).

## How is it kept?
- The route (`workspace.messages.canvas.edit`, `api_canvas.php`): POST with `also_accepts` PATCH (IIS). `version` must be the newest `version_no` (`GET …/canvas` gives it), else 409 `conflict`. Who: as for create (`ws_api_canvas_agent()`), on any canvas of a channel the owner may post in, an agent's or a person's.
- The edited document, `picture: pending` and `by` the agent, is a new `file_versions` row with source `agent` over the same pixels (the file keeps its last drawn picture until a browser draws the new one), then `pg_file_versions_prune()` keeps the original and the last ten.
- A canvas whose message a person wrote: its author gets the inbox line `canvas_edited` ("@handle edited your canvas in #channel", `ws_canvas_inbox_describe()`, actor 0), unless the owner of the application is that author.

## How does the person see and undo it?
- A person's canvas (message `sender_kind` user) whose newest version is an agent's edit (source `agent` with an earlier version) carries `file.canvas_edit` {handle, version, can_undo} (`ws_canvas_edit_mark()`); `workspace.js` `filePreview()` draws "Edited by @handle" under the picture and Undo when `can_undo` (the payload's `can_edit` and may post). The agent's own canvas carries no mark.
- `ws_canvas_message_state()` reads the handle from a 300-byte tail of the document starting at `"by":{` (`ws_canvas_by_handle()`, handle shape only), never the whole document; a quoted `"by"` inside a layer's markup is JSON-escaped and cannot match.
- Undo, panel action `ws_canvas_undo` {message_id, version} (`ws_canvas_undo()`, C3b #8299): `ws_message_edit_check()` must pass, the newest version must still be that agent edit (else refused), and the version before must not be an undrawn agent picture. Its pixels go back to the file and its document becomes a new version (source `restore`), so the edit stays among the versions; then prune, file size, `ws_message_touch()`. No API route: an agent edits again instead.
