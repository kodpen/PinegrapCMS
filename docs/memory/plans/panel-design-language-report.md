---
name: panel-design-language-report
description: Look A's report screen (#8955): ready reports and the period on top, four figures, the chart against the previous period, the grouped table and its total, the bars card, the settings drawer
---

## Where is it drawn?
- `plans/panel-design-language/report-page.html` with `report-page-dark.jpg` and `report-page-light.jpg`; approved by the owner on 2026-11-12 (#8955, `workflow/decisions-made-panel-8.md`). Tokens and shared parts: `panel-design-language-look.md`.
- The screen's parts today: `ui/report-view.md`, `ui/report-settings-drawer.md`, `ui/report-table-rows.md`. The drawing re-dresses them and adds no part; the levels, fields and options stay what the screens have.

## How is the page laid out?
- Content as wide as a list (`panel-design-language-sheet.md`; the page is drawn at the earlier 1280 px); 16 px between the blocks.
- Head: the report's name in the Display cut, an amber pill while it is unsaved; at the right "Export" and "Report settings" (quiet), then "Save" (filled).
- A strip under it: the label "Ready reports" and a segmented control of them.
- The tools row: the period as a segmented control, the date range as a 34 px field, a divider, each filter as a 32 px pill (the field's name in second text, the value in 550, a small close icon), "Add filter", and at the right end the switch that compares with the previous period.

## What do the figures and the chart look like?
- Four figure cards in one row, 16 px gaps, padding 15 18 14: a 13 px name beside its icon tile, the figure 26/650 in the Display cut with its unit smaller (15/550, second text), under it the change against the previous period as the up or down mark and a 12 px note.
- The chart card: padding 16 20 10. Head: the title (15/600), what it counts in 13 px third text; at the right the legend (a 2 px accent line, a dashed gray line for the previous period) and a segmented control choosing the figure drawn.
- Plot 232 px high: 1 px grid lines in the line colour, 11.5 px tabular axis text, the line 2 px in the accent over an 11 % fill, the previous period 1.5 px dashed. On hover: a 1 px crosshair, a ringed dot on each line, a floating tooltip (at least 196 px, radius 12) with the day and one row per line, the figure at its right end.
- One y axis: a second measure is another choice of the segmented control, never a second scale.

## What does the table look like?
- A frame with a 48 px head: the levels as the title (15/600), the period and count in 13 px, at the right the "Show detail" switch and the columns icon button.
- Heading row 36 px: 12/550 third text on the card fill, the sorted column in the first ink with its arrow. Rows 40 px, 16 px cell padding; every figure right-aligned and tabular; the first column takes the slack.
- Level 1 (a month): 600 on the card fill, a chevron before the name. Level 2 (a day): indented 42 px in second text, its total in the first ink. A share column (72 px, third text) after the total; a level-1 row draws a 64 x 4 px meter before its total.
- Grand total: 46 px, 650, a stronger top line and a 6 % accent fill; the table's last row.

## What is drawn under the screen?
- The bars card (620 px) for a level that is a name and not a date: rows 34 px, the name 150 px, a 10 px track filled with the accent gradient, the figure (550), the share. A level with no fitting chart shows one line saying so, never an empty plot.
- The report settings drawer, 430 px, built as the drawer of `panel-design-language-dialogs.md`: sections "Summarize by" (numbered levels, each a grip, a select and a remove button; "Add level"), "Filters" (field, operator, value, remove; "Add filter"), "Options" (rows with a switch). Foot with a line: "Apply" (filled), "Cancel" (quiet), "Reset" (ghost) at the right end.
