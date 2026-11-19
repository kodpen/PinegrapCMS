---
name: workspace-canvas-versions-api
description: CA15 canvas routes: an older version of a part put back, an agent's edit undone (person only, no MCP tool), the tables a page may bind; checks and dry runs from the panel's writers
---

## Which routes are there?
- In `api_canvases.php` beside the canvas routes ([[workspace-canvas-api]]); `{id}` is the message carrying the canvas.
- `POST /workspace/canvases/{id}/versions/{n}/restore` {`part` drawing|page}: `ws_canvas_part_restore()`, "Put back this version". Tool `workspace_canvases_versions_restore`.
- `POST /workspace/canvases/{id}/undo` {`part` drawing|page|binding, `version` or `line_id`}: `ws_canvas_undo()`, `ws_page_undo()` (on `ws_canvas_part_message()` of the part) or `ws_canvas_binding_undo()`. `'mcp' => false`.
- `GET /workspace/canvases/{id}/table-choices`: `ws_canvas_tables_choices()`, each as `table_id`, `name`, `bound` (read, write or empty). Tool `workspace_canvases_table_choices`.

## Who may call each?
- Restore: `ws_api_canvas_one_write_target()` (404 for a reader outside the channel, 403 for one who may not post), then the writer's own check.
- Undo: `ws_api_person_only()` first, so an application key gets 403; then the panel's rule: a drawing its writer while they may write, a page whoever may write in the channel, a binding whoever may bind or take off the table (`ws_canvas_binding_undo_check()`). A `line_id` that is no binding line of this canvas is 404; a line of an earlier version 422 (`ws_api_message_or_404()`).
- Table choices: a reader of a canvas with a page (`ws_canvas_page_reach()`, 404 otherwise); only tables the owner may write and the caller reaches (`ws_api_wtable_reachable()`, which narrows Claude's key), so a key is offered no table its channel membership does not give.

## How do the dry runs check?
- The four writers take `$check_only` (default false, the panel's calls unchanged): every check runs and they return before the first write. The route calls the writer with it, stops the dry run, then calls it again to write.
- The writers return `status` beside `error`; `ws_api_canvas_refusal_code()` maps it: 403 forbidden, 404 not_found (a version the part has not got), 409 conflict (the version shown now, the edit changed since, no picture yet, a binding undone or changed), 500 server_error, 503.

## Which PR?
- #9829, CA15 of the channel API comparison (`api-panel-coverage-channels.md`).
