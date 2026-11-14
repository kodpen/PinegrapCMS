---
name: agent-canvas-guide
description: The drawing guide an agent reads before drawing a canvas (canvas_guide.php): one set of values, its text, GET workspace/canvas/guide, the MCP resource and prompt
---

## Where do the guide's values live?
- `ws_canvas_guide_values()` in `includes/workspace/canvas_guide.php`: boards, scale base, grid, font, type, spacing, a dark and a light palette, marks, chart kinds. Built in #9457 from the guide sheet of #9400.
- Change a value there only: the text (`ws_canvas_guide_text()`), the short form (`ws_canvas_guide_short()`), the chart operation (`agent-canvas-chart.md`), the ready layouts (`agent-canvas-layouts.md`) and the live pages' Chart.js preset (`agent-page-libraries.md`) read it, and `tests/workspace_canvas_guide_test.php` checks every value appears in the text.

## Why is there a guide?
- Owner (2026-11-14, agent channel message 2296, on an agent-made board): "panoda chartlar aşırı basit olmamış mı? ajanın çizimlerinde yardımcı olacak guide leri yazılım tarafı sağlayabilirse sağlayalım. arayüz tasarımı olarak biraz daha iyileşme şart" (`workflow/decisions-made-workspace-24.md`).
- Before it, agents drew from hand SVG with no axis, values or units, a serif fallback or 12 px Arial read at half size, colours not the panel's and none for dark; Chart.js on a live page kept its defaults.
- Owner (2026-11-14, message 2660): the guide sheet (boards, grid, type, palettes, marks, chart kinds) approved as drawn; the values above are that sheet. Owner (message 2955): a line is 6 on the Card, not 3.

## Who reads the guide, and where?
- `GET /workspace/canvas/guide` (`workspace:read`, `ws_api_canvas_guide()`): `text` (English) and `values`.
- MCP: the resource `pinegrap://workspace/canvas-guide` over that route, and the prompt `canvas_chart` (channel_id, topic?), offered when the guide is readable and `workspace.channels.canvases.create` is a tool; it embeds the guide and says to send with layers split, dry_run first (`api/mcp-resources-prompts.md`).
- The descriptions of POST `/workspace/channels/{id}/canvases` and the canvas edit route end with the short form, which names the route.
- The assistants' `draw_canvas` carries the whole text in its description, since an assistant has no route to read it (`agent-canvas-assistants.md`).

## What does the guide hold back on?
- Weights only 300, 400, 600, 700, 900 (the editor snaps the rest); solid colours, no transparency, so every part splits into an editable layer (`agent-canvas-split-parts.md`).
- The palettes are tested against their boards: ink at least 7:1, ink2 4.5:1, ink3 and every series, up and down colour 3:1.
- A one-series chart takes the channel's colour at a tone that reads on its board; the guide text says so (`agent-canvas-chart.md`).
