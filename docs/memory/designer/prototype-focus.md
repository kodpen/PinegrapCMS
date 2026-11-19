---
name: prototype-focus
description: The Prototype view draws only the selected frame's arrows: the core's focus, opening on the open tab, All arrows, row marks, a shared part's link as an amber note, arrow colours and heads
---

## Which arrows are drawn?
- The view passes the graph canvas core's `focus` (ui/graph-canvas-core-focus.md): only the selected frame's arrows; out solid in the accent, in dashed amber (`--pgc-in`), a broken one red, each with its own head (`#sd-proto-arrow-on`, `-in`, `-broken`). Frames and chips at neither end are dimmed; the selected frame is outlined and its name in the accent.
- Opening the view selects the open tab's frame; a redraw keeps the selected frame while it is there. A press on a frame selects it, on the empty canvas none (nothing drawn).
- "All arrows" beside Fit (icon and name, `aria-pressed`) draws the others faint under them (`canvas.allEdges()`); kept per browser in `localStorage` `pg_proto_all_edges`.
- Architect (2026-11-16, agent channel message 6120): with "All arrows" on, the dimming still follows the selected frame's arrows only; a faint arrow brings no frame or chip back to full.
- The section headings of the arrangement are drawn by the core's `groups()` from `layout().headings`.
- The arrows run in right angles with rounded turns through the gaps between frames, to the nearest edge of their target, side by side where they share a gap; a moved frame routes its arrows again (`route: 'elbow'`, PV5; ui/graph-canvas-core-routing.md).

## What does a shared part's link into the selected page do?
- It draws no arrow (`sd-proto-from-shared` hides the in arrow in CSS). Its mark on the shared frame's picture turns amber and a line under that frame names it ("Cart icon → cart", `div.sd-proto-note`). Selecting the shared frame draws its own arrows. The focus announcement still counts it as one in.

## Which marks show?
- `PGProtoHotspots.focusOf(layout, id)` answers `{ out, in, shared, lit }`; `_sdProtoFocusMarks()` runs on every change (`adapter.focusChanged`) and after each picture loads: the marks of `markState()` (designer/prototype-marks.md), the notes under shared frames.
- Tests: `test_proto_hotspots_focus_of_a_frame`, `test_proto_hotspots_view_focus_and_all_arrows`.
- The canvas stays dark under the light panel theme (architect, 2026-11-16, agent channel message 5878).
