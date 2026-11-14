---
name: agent-canvas-chart
description: The chart canvas operation (canvas_chart.php): kinds, data, boards, how the server draws it by the guide, the channel's colour for one series, the refusals, create and draw_canvas
---

## What does the chart operation take?
- `{op: chart}` in a canvas edit (route, MCP tool, `edit_canvas`), or `chart` instead of svg on POST `/workspace/channels/{id}/canvases`, or `draw_canvas`'s `chart` (a JSON object in a string). Built in DG3 of `plans/agent-drawing-guides.md`.
- kind (bar, stacked_bar, line, area, donut, sparkline, figure_tile), theme dark or light (light by default), board card (default), slide, square, portrait or story; title, subtitle, note; labels and values, or series of up to five {name, values}; highlight; format en or tr (31.2K / 31,2 B).
- A figure tile and a sparkline have their own boards (600 x 360, 600 x 120) and take label, value, values; a tile also delta and vs.

## How is it drawn?
- `ws_canvas_chart_check()` checks and fills the defaults; `ws_canvas_chart_svg()` writes SVG by `ws_canvas_guide_values()` (`agent-canvas-guide.md`), in base units under a viewBox scaled to the board, each part a top-level element with an id.
- Lines are 6 on a scaled board, so a card shown at half size reads as wide as a tile's or sparkline's trend of 3; the axis takes one more step when the top value would end near the top gridline, so its label has room.
- It is laid in as the svg operation with layers split: the whole canvas anew, texts as text layers in `g:Inter`, marks as svg layers. Redrawing with changed data is the same operation again.
- The test opens every kind's document with the editor's `M.canvasOpenDoc()` in Node (`tests/workspace_canvas_chart_test.php`).

## Which colour does a chart take?
- Owner (2026-11-14, to the coordinator): "chartlarda birden fazla renk kullanılmayacaksa chart göstergeleri kanalın rengini alabilir mi?" A one-series chart (bar or line of one series, area, sparkline, figure tile) takes the colour of the channel it is drawn in (`ws_canvas_chart_channel_tone()`). Confirmed to the architect ("Evet, alsın"): decisions list, workspace part 27.
- `ws_canvas_chart_tone()`: the channel colour when it reaches 3:1 on the board, else its own hue lightened (dark board) or darkened (light) in OKLab until it does, else the guide's first series colour; no channel colour gives the guide's first too.
- Several series and the donut keep the guide's palette. The colour is written into the layers: a later change of the channel's colour does not repaint an old canvas; drawing it again does.

## What is refused?
- 422 on operations (edit) or chart (create), codes `chart_kind`, `chart_theme`, `chart_board`, `chart_words`, `chart_data` in `ws_canvas_edit_error_text()`: a negative or non-number value, a series whose length is not the labels', more than five series, a donut of more than six slices or of several series, a line or area of one label, a highlight outside the labels.
