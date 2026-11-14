---
name: agent-canvas-overview
description: Start file of the agent-canvas-* cluster: an agent designs on a Workspace canvas through the API, MCP and the assistants' tools; the binding decisions, the parts and which file holds each
---

## What is it?
- An agent (an application of the channel, Claude, Pinegrap AI or a model assistant) makes a design as a canvas: a message whose picture opens in the Design Canvas with its layers, as if a person had made it. A chart, a banner, a post picture or a slide is a canvas.
- Owner (2026-11-11, #8762): no second tool beside the canvas; it is strengthened until a design comes out through the API, in every channel the agent is added to (`workflow/decisions-made-workspace-16.md`).

## What binds every part?
- One set of operations for the API route, the MCP tool made from it and the assistants' `edit_canvas`; a new operation goes into all three and into the route's `of` list, which `check_api_schema.php` reads.
- A layer the server writes is one the editor's model (`assets/js/pg_image_editor_model.js`) accepts: each kind has a test that runs the same step through the model in Node.
- The limits: artboard 16 to 8000 pixels, a document of 1 MB, 20 operations a call, 30 scenes for an agent.
- Each write is a new version made by the agent (kept with the original and the last ten), its picture pending until the server or a member's browser draws it.
- Owner (2026-11-11, #8762): the server's drawing is optional, never required; a host without a usable Imagick loses nothing, a member's browser draws there (`agent-canvas-picture.md`).
- A new limit or a schema change is named in its own PR with the reason; a column is asked first (`CLAUDE.md`, Fixed rules 5).

## Which file holds what?
- Drawing a canvas from SVG and reading one, who may: `agent-canvas-svg.md`.
- The SVG taken apart into a layer per element: `agent-canvas-layers.md`; texts and pictures as their own layers: `agent-canvas-split-parts.md`.
- Edits in place (the operations, 409 when stale, the agent's version, Undo): `agent-canvas-edit.md`.
- Layers written one by one (text, picture, shape, icon, style, order, name, lock, the fonts route): `agent-canvas-layer-writes.md`.
- The board's size, background and scenes: `agent-canvas-scenes.md`.
- Templates a person marks and an agent fills: `agent-canvas-templates.md`.
- The drawing guide an agent reads before it draws (boards, grid, type, palettes, chart rules): `agent-canvas-guide.md`.
- The picture, drawn by the server where it can, else by a browser: `agent-canvas-picture.md` (the server's drawing: `platform/image-server-draw.md`).
- Saving the picture into Files under the risk tiers: `agent-canvas-save.md`.
- The assistants' tools (`draw_canvas`, `read_canvas`, `edit_canvas`): `agent-canvas-assistants.md`.

## What is not part of it?
- Live pages an agent writes in HTML: `agent-page.md`.
- Translating the words in pictures in the background on the server's drawing (owner 2026-11-11, #8762): wanted, not planned yet; it would be a plan of its own.

## How is a new part added?
- The pure step goes into its own file under `includes/workspace/canvas_*.php`, called from `ws_canvas_edit_apply()` (`canvas_edit.php`) with its refusal code and sentence in `ws_canvas_edit_error_text()`.
- The route's description and `of` list (`api_canvas.php`), the assistants' tool text (`canvas_ai.php`) and the read (`ws_canvas_read()`) follow in the same PR, with a test in `tests/workspace_agent_canvas_*_test.php` and its topic file here.
