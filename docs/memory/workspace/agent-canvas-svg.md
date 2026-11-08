---
name: agent-canvas-svg
description: Agents draw Workspace canvases from SVG (POST channels/{id}/canvases) and read any canvas (GET messages/{id}/canvas): Import port, blank picture, pending, who may
---

## What are agent canvases for?
- An agent shows something as a picture in a channel where it is a member and changes it on request, as Claude's artifacts: it writes SVG, people open it in the image editor (owner 2026-11-08, `workflow/decisions-made-workspace-9.md`). Claude, Pinegrap AI and model assistants alike (`agent-canvas-assistants.md`). No schema: source `agent` and the document's `by` name the agent. Built in #8236, #8254, #8252, #8299, #8293; picture `agent-canvas-picture.md`, edits, mark and Undo `agent-canvas-edit.md`. Notes' canvases: not yet.

## What does an agent's SVG become?
- `ws_canvas_from_svg($svg, $background)` (`includes/workspace/canvas_svg.php`) builds the image editor's canvas document as Import does (`ui/image-editor-import.md`): `pg_svg_sanitize()`, then one `svg` layer (id `l0`, kind `g`, `imported`) covering the artboard; an outer `<g id>` with the root's id (`svg` when none), an inner `<g transform>` with the view box's matrix and the root's inherited paint; ids prefixed `i<n>-` and references renamed.
- The PHP port of the model's `svgRoot()`, `svgMatrixText()`, `svgIdMap()`, `svgRenameValue()` and `svgRenameCss()` is checked against the model under Node in `tests/workspace_agent_canvas_test.php`: change one side, the test fails.
- Artboard: the root's width and height (view box when missing, 300 x 150 when both are), each side 16-8000; fill `#rrggbb` or `transparent`, white by default. Definitions stay inside the layer's markup, as Import keeps them (no `svgDefs`).
- Refused (422 on `svg` or `background`): over 1 MB, nothing left to draw after the sanitizer (definitions only count as nothing), a picture as a `data:` URL (the document could not be kept, `pg_file_versions_doc_storable()`), a fill that is no colour.

## What does the message carry?
- A PNG file of the artboard's size in its fill, `ws_canvas_blank_png()`: a hand-built one-colour palette PNG (1 bit a pixel), so an 8000 x 8000 board is a few kB and needs no GD memory. The server never rasterises SVG.
- The document is kept as the file's newest `file_versions` row with source `agent` (`pg_image_editor_canvas_keep()`), user the application's owner, and carries `picture: "pending"` and `by: {id, kind, handle}` (the agent). A browser draws the picture later (plan step C2); until then the read says `picture: pending`.
- `by` is read only on versions whose source is `agent`: the editor opens the whole document and a person's save carries `by` along, but that version is theirs (`saved`/`new`).

## Who may make one?
- `ws_api_canvas_agent()`: Claude's application where Claude may be asked (`ws_api_channel_or_404()`), or an application with an allowed `app` row in `ws_channel_agents` for the channel (`agent-registry.md`), in a channel the owner may post in. A person's own device (team devices session) is refused 403: people draw in the panel's editor. Before the versions table exists the route answers 503.
- The message is written as the application (`ws_api_writer_app_id()`), with optional `text`, `name` and `parent_id`; a dry run makes every check.

## What does the read give?
- `GET /workspace/messages/{id}/canvas` (scope `workspace:read`, any reader of the message): artboard, `url`/`api_url` of the picture, `picture` pending or drawn, `version` (newest version_no), `versions` (source, user_id, agent_id and agent handle for an agent's version, created_at), `layers` bottom up (id, type, kind, box, locked; a text layer's text and colour; an svg layer's markup). 404 when the picture is no canvas or changed outside the editor since (`ws_canvas_open_doc()`).
- Answers are declared inline (`ws_api_canvas_returns()`), not as named objects, so `api.php` holds only the require and the merge of `ws_canvas_api_routes()`. MCP tools `workspace_channels_canvases_create` and `workspace_messages_canvas_get` come from the rows.
