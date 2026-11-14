---
name: agent-canvas-guide
description: The drawing guide an agent reads before drawing a canvas (canvas_guide.php): one set of values, its text, GET workspace/canvas/guide, the MCP resource and prompt
---

## Where do the guide's values live?
- `ws_canvas_guide_values()` in `includes/workspace/canvas_guide.php`: boards, scale base, grid, font, type, spacing, a dark and a light palette, marks, chart kinds. Built in DG2 of `plans/agent-drawing-guides.md` from the owner-approved DG1 sheet (`agent-drawing-guides-census.md`).
- Change a value there only: the text (`ws_canvas_guide_text()`), the short form (`ws_canvas_guide_short()`) and DG3's chart writer read it, and `tests/workspace_canvas_guide_test.php` checks every value appears in the text.

## Who reads the guide, and where?
- `GET /workspace/canvas/guide` (`workspace:read`, `ws_api_canvas_guide()`): `text` (English) and `values`.
- MCP: the resource `pinegrap://workspace/canvas-guide` over that route, and the prompt `canvas_chart` (channel_id, topic?), offered when the guide is readable and `workspace.channels.canvases.create` is a tool; it embeds the guide and says to send with layers split, dry_run first (`api/mcp-resources-prompts.md`).
- The descriptions of POST `/workspace/channels/{id}/canvases` and the canvas edit route end with the short form, which names the route.
- The assistants' `draw_canvas` carries the whole text in its description, since an assistant has no route to read it (`agent-canvas-assistants.md`).

## What does the guide hold back on?
- Weights only 300, 400, 600, 700, 900 (the editor snaps the rest); solid colours, no transparency, so every part splits into an editable layer (`agent-canvas-split-parts.md`).
- The palettes are tested against their boards: ink at least 7:1, ink2 4.5:1, ink3 and every series, up and down colour 3:1.
