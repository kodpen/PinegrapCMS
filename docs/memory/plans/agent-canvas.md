---
name: agent-canvas
description: Plan (owner 2026-11-08): agents create, read and edit Workspace canvases by writing SVG, in any channel with an agent; the channel's browser draws the picture; C1 done (#8236), C2-C4 left
---

## What is it for?
- An agent shows something as a picture in a channel and changes it on request, as Claude's artifacts do: it writes SVG, people see the canvas and open it in the image editor with its layers (owner 2026-11-08, `workflow/decisions-made-workspace-9.md`). In agent channels and in ordinary channels where an agent is a member; Claude, the built-in assistant and model assistants the same way. It replaces A9 of `agent-channel-steps-2.md` (document and PNG sent by the agent).

## What exists already?
- A Workspace canvas is a PNG file in a message, its editor document in `file_versions` (`includes/workspace/canvas.php`, `ui/image-editor-workspace.md`). The browser draws the PNG; the server never rasterises SVG (`ui/image-editor-overview.md`).
- `pg_svg_sanitize()` (`fn/svg_sanitize.php`) keeps an allowlist. The editor's Import makes an SVG one `svg` layer (kind `g`, `imported`), Break apart a layer per element; an `svg` layer paints on a canvas (`ui/image-editor-import.md`).

## How should it work?
- Create: the agent sends SVG (optional message text, `parent_id`). The site sanitizes it, refuses an empty result or over 1 MB, and builds the document in PHP as Import does: the artboard from the root's width, height and view box (the editor's limits), one imported `svg` layer covering it, definitions in `svgDefs`; a blank picture of the artboard's fill (GD); the document marked `picture: pending`.
- Picture: the first member's browser that shows a pending canvas draws it with the editor's renderer (`M.canvasOpenDoc()`, `drawArtboard()`) and posts the PNG once (`workspace_file_save.php` action `canvas_picture` with the version number; `ws_canvas_picture()` checks the size). Taken only while pending, from a member who may write in the channel.
- Read: artboard, picture address, version number, who made each version, the layers (id, type, kind, box; a text layer's text and colour; an svg layer's markup). An agent that sees pictures looks at the PNG too.
- Edit any canvas of the channel in place, the agent's or a person's (owner 2026-11-08): operations on the newest version, 409 when `version` is stale: `add` (SVG as a new imported layer, a box optional; ids prefixed as `M.svgIdMap()` does), `replace` (an svg layer's markup), `remove`, `text`, `box` (move, resize), `svg` (the whole canvas anew). Each edit is a new version (source `agent`, the agent in the document's `by`; the original and the last 10 kept as today), its picture pending again.
- A person's canvas edited by an agent: the message shows "Edited by @handle" with Undo (the version before as a new one, for whoever may edit the message), and its author is notified. No schema: the version's source and `by` name the agent.
- Who: an application key with `workspace:write` that is a member of the channel (A3's channel-agent row); the routes are MCP tools too (`api/api-mcp-server.md`).

## Steps (claim as `[plan:agent-canvas] C<n> — <topic>`)
- C1 (M) Create and read: done in #8236 (`workspace/agent-canvas-svg.md`).
- C2 (M) The channel draws a pending canvas and posts its picture. After C1; a browser run (the renderer needs one).
- C3 (M) Edit: `PATCH /workspace/messages/{id}/canvas` with the operations, the mark, Undo, the notification. After C1, beside C2.
- C4 (S) The built-in assistant and model assistants: the same as `ws_ai_step()` tools. After A4.

## Open questions
- None for the owner. Notes' canvases later.
