---
name: agent-canvas-assistants
description: Pinegrap AI and model assistants draw, read and edit Workspace canvases as ws_ai_step() tools (canvas_ai.php): when offered, who the version names, what the model reads
---

## Which tools, and when are they offered?
- `includes/workspace/canvas_ai.php` (required at the end of `ai.php`): `draw_canvas` (svg, background, text, name), `read_canvas` (message_id), `edit_canvas` (message_id, version, operations). `ws_ai_tools()` adds them and `ws_ai_tool_run()` sends them to `ws_canvas_ai_tool_run()`; their names are in `ws_ai_tool_names()` so a call written into the words is made too.
- `ws_canvas_ai_offered()`: a request asked in a channel (not a note, task, page or MCP server alias), the versions table there, the assistant's application set and `ws_assistant_writer()` finding a reader who may post in the channel. The same for Pinegrap AI and for a model assistant (`model:<id>` request, run through the same loop, `model-assistants.md`).
- Read and edit reach only canvases of the request's own channel (`ws_canvas_ai_target()`: not deleted, not in an earlier version of the conversation).

## What do they do?
- The same as the API routes (`agent-canvas-svg.md`, `agent-canvas-edit.md`): `ws_canvas_from_svg()`, a blank picture posted as the assistant's application under the request (`parent_id` the request's message), the document kept as a `file_versions` row with source `agent`, `picture: pending` until a member's browser draws it (`agent-canvas-picture.md`). An edit is `ws_canvas_edit_apply()` on the newest version, a stale `version` refused, then `pg_file_versions_prune()`, the author's `canvas_edited` inbox line for a person's canvas and `ws_message_touch()`.
- `by` in the document (`ws_canvas_ai_by()`): a model assistant as `{id, kind: model, handle}` from `ws_model_running()`, Pinegrap AI as `{id: 0, kind: ai, handle: ai}`.
- Arguments stay flat: `operations` is a JSON list in a string (`ws_canvas_ai_operations()`; one object alone is a list of one).
- A drawn or edited canvas sets `$state['canvas_done']`, so the answer "I drew it" is not sent back as a claim of a change nobody proposed (`ws_ai_finish_answer()`).

## What does the model read?
- `ws_canvas_ai_present()` over `ws_canvas_read()`: message id, artboard, picture, version, layers and versions without empty fields, an svg layer's markup cut at 3000 characters (the tool answer is cut at 12000 as a whole), times as UTC dates.

## Tests?
- `tests/workspace_canvas_ai_test.php`: the maker, the operations, the read, the names against `ws_ai_tool_names()` and flat arguments. The tool runs need a database and a model; not run live.
