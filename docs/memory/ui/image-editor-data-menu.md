---
name: image-editor-data-menu
description: The Artifact Canvas's Data menu (DV2 #10892, DV2b): the top bar's Data button, the two-step picker, the layer's panel groups, the actions data_tables and data_rows
module: image_editor_data
---

## Where is the Data menu?
- A "Data" button on the canvas's top bar right after Import (`Editor.topData`, spliced after `Editor.topImport` in `pg_image_editor_data.js`): a bar-chart icon and the word, the icon alone under 576 px as Import (`.pgie-databtn`). Shown with the editor's API wherever layers can be added (the Select tool). Plan: `plans/canvas-data-views.md`; screens: `plans/canvas-data-views-screens.md`.
- DV2 put it as an entry inside the Import menu; the owner did not find it there (2026-11-21), so DV2b gave it its own button and the Import menu no longer lists it.
- It opens a picker over the editor (the Import box's frame, `.pgie-databox`): step 1 "Pick a table", a search and the tables as rows (name, `#channel` or "Not in a channel", row count); step 2 "Pick a view", a card per kind with a sample drawn from the table's first 50 rows (`M.DATA_SAMPLE_ROWS`) and the kind's name. A card puts the layer on the stage and closes the picker; no Add button.

## Which kinds does a table fit?
- `M.dataColumns()`: a column is a number when it has a number among its first 200 rows and every other non-empty cell there is one. `M.dataFits()` gives Table always (one column is enough), a chart kind only with a number column, Stacked bar with two, Line and Area with two rows. With Table alone the picker and the panel say "Only a table fits: this table has no number column."
- `M.dataStart()`: the label is the first column that is no number, the values the number columns beside it (five; a donut one; a plain table any five columns); a label that comes again is grouped with Sum; the title is the table's name.
- A new layer is 60 % of the canvas wide at 16:9, in the middle, selected (`dataPlace()`).

## How is the panel laid out?
- `layerTypes.data.panel` for one selected data layer, three groups folded one at a time (`.pgie-more` with a chevron, `this.dataFold`), Data open first:
  - Data: the table with Change (the picker again; a card gives the layer the new table in its box), Label column, Value columns (checkboxes, five at most, a chart keeps one; a donut a select), Grouping (None, Sum, Count, Average as segments), Sort and its direction, Row limit (a slider; the cap means 0).
  - Look: the kind as sample cards of the kinds that fit (`M.dataWithKind()` keeps what the new kind takes), Colours (Palette, Channel when the table has a channel colour, Chosen with a swatch row per series or slice), Legend (Off, Top, Bottom) for several series.
  - Text: Title (one undo step while typed), Number format (12.3K / 12,3 B), Type size (Small, Medium, Large).
- A donut shows no sort or row limit. A table the reader may not read shows "You cannot read this table." in Data.

## What do the actions answer?
- `includes/fn/image_editor_data.php`, reached by `pg_image_editor_api()`'s action dispatch. `data_tables`: `ws_wtables_reached()` (200 at most) as `{id, name, channel, rows, tone}`; `tone` is the channel colour on each board (`ws_canvas_chart_tone()`), null without one. No Workspace: an empty list.
- `data_rows {table}`: `ws_canvas_data_rows()` with the reader's rights, `{read}` as `editor.dataRead()` takes it (columns and cells kept JSON objects). A data layer on the stage whose rows are not read asks once (`dataLoad()`).
- DV2 writes no binding (`ws_canvas_tables`).

## How is it checked?
- `tests/image_editor_data_menu_test.php`: the fits and starts, the row limit, the picker and the panel on a bare editor under Node, the server's table rows. New description fields in the shared fixture (`legend_under`, `limit_small`) for the model and the PHP port.
