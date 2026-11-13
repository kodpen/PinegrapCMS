---
name: workspace-canvas-api
description: The canvas as one thing in the API and MCP (CO7, #9231): GET /workspace/canvases/{id} with its parts and one list of versions, tables bound and taken off, a new version of a part
---

## Which routes are there?
- `includes/workspace/api_canvases.php` (`ws_canvases_api_routes()`, merged in `api.php`), all under `workspace:read` / `workspace:write`. `{id}` is the message carrying the canvas, as every older canvas address is.
- `GET /workspace/canvases/{id}`: `id`, `name`, `channel_id`, `parts` and `versions`. 404 for a message carrying neither a drawing nor a page.
- `POST /workspace/canvases/{id}/tables` {`table_id`, `access` read|write}: binds a workflow table, or changes a binding's access.
- `DELETE /workspace/canvases/{id}/tables/{table_id}` with the `…/delete` POST twin (`…unbind_post`, no MCP tool).
- `POST /workspace/canvases/{id}/page` {`version`, `html`, `libraries`} and `…/drawing` {`version`, `svg`, `background`, `layers`}, PATCH accepted too: a new version of that part. Every write answers the canvas as the GET gives it.
- The tools follow from the rows: `workspace_canvases_get`, `workspace_canvases_tables_bind`, `workspace_canvases_tables_unbind`, `workspace_canvases_page_put`, `workspace_canvases_drawing_put`.

## What are the parts and the versions?
- `parts.drawing` (`artboard`, `picture`, `layers` as a count, `version`), `parts.page` (`size`, `version`, `libraries`), `parts.data` (the bound tables: `id`, `name`, `access`, `reached`, `row_count`). A part the canvas has not got is null; `data` is a list, empty when nothing is bound.
- A table the caller's owner does not reach is counted with an empty name, `reached` false and no row count, as the card's line of tables does (`workspace/canvas-tables.md`).
- The page part is read from its version rows alone, never by loading the document: its bytes are fetched by `GET /workspace/messages/{id}/page`.
- `versions` is one list over the parts, oldest first (`ws_canvas_versions_merge()`, pure): `part`, `version`, `source`, `user_id`, `agent_id`, `agent`, `size` (the page's alone), `created_at`. Each part keeps its own numbering, and versions of the same second stay in their part's order.

## Who may, and what is refused?
- Reading: any reader of the channel, as `GET /workspace/messages/{id}/canvas` is.
- Binding and taking off: the owner may write in the channel and may change the table, for a read binding too (`ws_canvas_table_bind_check()`, `ws_canvas_table_unbind_check()`, shared with the panel's actions). Refusals: 404 `not_found` for a table out of reach or a binding that is not there, 403 `forbidden`, 409 `too_many_tables` over the 20 a canvas, 409 `part_missing` for a canvas with no page (a drawing reads no table; add the page first), 422 on `access`.
- A key's binding is kept as `bound_by_kind` `app` with the application's id (`ws_api_canvas_table_binder()`, pure); from a team devices session the person binds in their own name.
- Writing a part: an agent of the channel (`ws_api_canvas_agent()`), as the create routes ask; a person's device 403. A stale `version` is 409 `conflict`.
- `version` 0 on the page or drawing route adds that part beside the first (#9258); any other version on a missing part, or a part another call added first, is 409 `conflict`. How parts are kept: `workspace/canvas-parts.md`.

## Where is the write itself done?
- Nowhere new: `ws_api_page_new_version()` (api_page.php) and `ws_api_canvas_new_version()` (api_canvas.php) hold what the message routes did, and both addresses call them, so the version check, the pruning, the picture, the author's inbox line and `ws_message_touch()` happen once.
- Tests: `tests/workspace_canvas_api_test.php` (the merged versions, the rows, the tool names, the parameters, the binder). The handlers need a database; not run live in this step.
