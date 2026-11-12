---
name: canvas-one-model
description: CO0 model of plans/canvas-one.md: what one canvas is in the store and the API: the message as its id, the drawing, page and data parts, one list of versions, the routes and the bridge's calls
---

## What is one canvas in the store?
- A message of a channel, as today: its id is the canvas's id, so `messages/{id}/canvas`, `messages/{id}/page`, `live_page.php?message=<id>` and the cards keep working. Nothing made before is converted.
- Parts: `drawing` (the image editor's document, `file_versions` rows with the canvas `doc_json`), `page` (a live page, `file_versions` rows with `format = 'html'`, `workspace/agent-page.md`), `data` (the tables bound to it). A part that is not there is not shown.
- The message's own `file_id` is its first part. A second part needs a place the message does not have: a row naming the part's file (schema S1). The name is the message's `file_name`.
- Data is the list of bindings (schema S2): a table, `read` or `write`, who bound it. A canvas's page reaches only these.

## What is a version of a canvas?
- No new store: the `file_versions` rows of its part files, merged by `created_at`, each with `part`, `version` (its `version_no`), `source`, the maker (`by`). A binding added or removed is a line in the channel, not a version.
- Each part keeps its own 409 check (`version` of that part), as the canvas and page routes do now; Undo stays per part.
- The first-write question is asked again when the page part gets a new `version_no` (schema S3).

## What are the API names?
- `GET /workspace/canvases/{id}` (`workspace:read`): `id`, `name`, `channel_id`, `parts` {`drawing`: artboard, picture, layers count or null; `page`: size or null; `data`: tables [{`id`, `name`, `access`, `row_count`}]}, `versions` as above.
- `POST /workspace/canvases/{id}/tables` {`table_id`, `access`} and `DELETE /workspace/canvases/{id}/tables/{table_id}`: bind and unbind; the caller may edit the canvas (its channel) and the table (`ws_wtable_may(…, 'write')`, for `read` too, so nobody binds a table they could only look at to a page others run).
- `PUT /workspace/canvases/{id}/page` {`version`, `html`} and `…/drawing` {`version`, `svg`}: adds or rewrites a part; the old message routes stay as they are.
- MCP tools come from the rows: `workspace_canvases_get`, `workspace_canvases_tables_bind`, `workspace_canvases_tables_unbind`, `workspace_canvases_page_put`, `workspace_canvases_drawing_put` (CO7).

## What does the page ask the shell?
- One message shape both ways, `pg: 'canvas-data'`: the page posts {`id`, `call`, `args`}; the shell answers {`id`, `ok`, `data`} or {`id`, `ok: false`, `error` {`code`, `text`}}. The shell takes it only from its own frame (`event.source`) and passes it to the panel; the panel adds the canvas id of the card that made that shell: a page never names its canvas.
- Calls: `who` (CO1: the reader's display name), `tables` (the bound list), `rows` {`table`, `where`, `sort`, `dir`, `limit`, `totals`} (CO2, `workspace/workflow-tables-query.md`), `add` {`table`, `cells`} and `change` {`table`, `row`, `revision`, `cells`} (CO3). `table` is a bound table's id; any other id answers `not_bound`.
- Errors: `not_bound`, `forbidden` (the reader's rights), `consent` (the reader said no), `limit` (bounds), `changed_meanwhile`, `invalid` with the field.

## Which schema changes do the steps need?
- In `canvas-one-schema.md`, each with its issue.
