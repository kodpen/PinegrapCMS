---
name: canvas-data-views
description: Plan (owner 2026-11-20): a Data table shown on a canvas as a chart or a table, a page the site writes from a short description and that reads the table itself, so it follows the data; DV1-DV5
---

## What did the owner ask?
- Owner (2026-11-20, in the architect's window; agent channel message 9848): a table or a calculation turned into a chart on a canvas by itself: at the table's top "tuvalde chart olarak" and "tuvalde tablo olarak sun"; the chart's kind chosen from small samples of the data; design and colours changed afterwards on the canvas; "veride değişiklik olursa tuvaldeki veri de güncellenir"; a picture saved from it does not follow.
- Why this way, his words: the channel gets "ajanların da veri olarak koyduğu chart html li şekilde", and "veri değişiklikleri için ek tuval çizme bedeli ödenmez". Record: `workflow/decisions-made-workspace-41.md`.

## What is there today? (read in memory on 2026-11-20, not tried)
- A canvas holds a drawing, a live page and bound tables; a page reads only its canvas's tables through the bridge with the reader's rights (`workspace/canvas-overview.md`, `workspace/canvas-bridge.md`); Chart.js with the chart guide's look is the site's own copy (`workspace/agent-page-libraries.md`).
- A chart drawn by the server (`workspace/agent-canvas-chart.md`) is a picture: it does not follow the data. A page that follows it is written by an agent today.

## What is built?
- DV1, the view on the server. A canvas's page may be a "data view": a short description (chart or table, the table, the kind, the label column, the value columns, grouping with sum, count or average, title, colours, sort, number format) from which the site writes the page (`chart_guide` library, rows read through the bridge). The description is kept in the page version's document, so the Versions drawer lists each change.
- DV1: the API and MCP make and change one by the same description; an agent writes no HTML for a chart.
- DV2, the Data screen. A table's head gets "Show on a canvas" with "As a chart" and "As a table". A chart asks its kind from samples drawn with the table's own first rows (bar, stacked bar, line, area, donut; only the kinds its columns fit); then the channel (the table's own first). The canvas is made with the table bound read-only and its card stands in the channel.
- DV3, following the data. The view reads the rows when its card is shown and when the panel tells the frame that a bound table changed; the page itself never polls. A change costs no drawing and no model call. A calculation is the view's grouping or the table's formula columns, worked out on read.
- DV4, design on the canvas. "Edit view" on the card: kind, columns, grouping, title, legend, number format, and colours (the guide's palette, the channel's tone, or chosen ones); a chart shows its figures as a table on demand (`ui/chart-text-equivalents.md`). Each save is a version.
- DV5, the table view and the picture. The table view's columns, order, filter and a totals row; both views at phone width; "Save as picture" puts a still copy in the channel that says its date and does not follow.
- Order DV1 to DV5, one PR each; a and b where a step passes the size.

## Which rules does every step keep?
- No schema foreseen: the description lives in the page version's document; a step that needs a column stops and asks the architect.
- The guarded frame's rules stay: no outside script, no connection of the page's own, only bound tables, the reader's rights; a guest sees what the guest page shows of a page today (`workspace/guest-page-content.md`).
- The bridge's bounds stay (`workspace/canvas-measures.md`); a view over the 5000-row limit says what it left out.
- A view writes nothing to its table. Texts through `lang()`; look A; unit tests for the description's check and the written page, the bridge's tests kept.

## How does the plan close?
- A `workspace/canvas-data-views.md` says the state, `workspace/canvas-overview.md` names it, this plan is deleted.
