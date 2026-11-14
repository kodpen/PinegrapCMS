---
name: agent-canvas-layouts
description: Ready layouts (DG4): four canvas templates shipped with Pinegrap, drawn from the drawing guide in light or dark, listed beside people's templates and filled by layout, theme, fill and chart_values
---

## What are they?
- Four templates the software ships, built in `includes/workspace/canvas_layouts.php` from `ws_canvas_guide_values()` (`agent-canvas-guide.md`), so they follow the guide when it changes: `figures_chart` (card: three figures with their change over a bar chart), `report_page` (portrait: two figures, a summary, a bar chart), `comparison` (card: two panels, each a figure, a caption and three points), `slide` (slide: title and three points at the left, a bar chart at the right).
- Every word a person can see goes through `lang()`: the placeholders (numbered ones as `Label {var:1}`) and each layout's title and use (`ws_canvas_layout_words()`); the title is the canvas file's name when the agent gives none. Architect (2026-11-14, channel 25 message 2799): no raw English in what a person sees.
- Each comes in the guide's `light` or `dark` board palette. No file, no row, no column: the document is drawn on each request.

## How is a layout drawn?
- `ws_canvas_layout_operations()` writes `add` operations (text, shape rectangle, shape line) in the guide's sizes, multiplied by the board's scale; `ws_canvas_layout_doc()` applies them to a blank artboard through `ws_canvas_edit_apply()`, 20 at a time, so every layer is one an agent's own add would make and the editor holds.
- Every layer is named; text never wears a series colour, a figure's change is in up or down with an arrow. The chart is fixed shape and text layers (gridlines, baseline, `chart_axis_0..4`, `chart_bar_N`, `chart_label_N`, `chart_value` over the last bar); a bar's corners are all rounded, since a rectangle layer has one radius.

## How does an agent use one?
- `GET /workspace/canvas/templates` answers `layouts` beside `templates`: name, title, use, themes, width, height, `chart_values` (how many bars, 0 for none) and the named layers with their words.
- `POST /workspace/channels/{id}/canvases` with `layout`, `theme` (light by default) and `fill`: names => words for text layers (as a template's fill, `ws_canvas_template_fill()`), and `chart_values`, a list of exactly that many numbers from 0 up, which redraws the bars, a round value axis (4 steps of 1, 2, 2.5 or 5 × 10^n) and the last value. Exactly one of svg, template and layout, else 422 on svg.
- Refusals 422: an unknown layout (layout), a theme not light or dark (theme), a fill name the layout lacks or a shape named (fill, with the layers listed), chart_values of the wrong count, negative or not numbers, or given to `comparison` (fill).
- The MCP prompt `canvas_chart` tells the agent to send a layout when one fits. The assistants' tools do not offer templates or layouts.

## What could change later?
- DG3's `chart` operation can replace the fixed chart layers; DG4 did not use it, as it had not landed.
- Axis and value numbers are grouped with a dot (31.200) whatever the channel's language; the agent can overwrite them through fill.

## Tests?
- `tests/workspace_canvas_layouts_test.php`: boards, palettes, type sizes and names of every layout in both themes; the list; fill, chart values and refusals; every text box and the document's fields through the editor's model in Node; the route rows and the prompt.
