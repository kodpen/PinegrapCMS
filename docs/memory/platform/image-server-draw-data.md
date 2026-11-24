---
name: image-server-draw-data
description: The server's draw of a Design Canvas data layer (DV1b): the PHP port of the model's marks, the reader the caller hands in, what waits for a browser, the one row read with the reader's rights
module: image_draw_data
---

## What does the server draw of a data layer?
- `includes/fn/image_draw_data.php` ports the model's DATA section (`ui/image-editor-data-layer.md`): `pg_image_draw_data_check()` (the same codes and defaults as `M.dataCheck()`), `_series()`, `_format()`, `_step()`, `_marks()`. Same marks to the hundredth: `tests/image_draw_data_test.php` holds every case of `tests/fixtures/canvas_data/layers.json` alike with the model's.
- JavaScript's habits are ported with it: `Math.round()` halves up (`pg_image_draw_data_js_round()`), `String()` of a number, `!!` truth, `Array.isArray()` of decoded JSON. A change of the model's drawing changes the fixture, the PHP port and both tests in one PR.
- `image_draw.php` requires it. `pg_image_draw_plan()` and `pg_image_draw_document()` take a fifth/third argument `$data`: a table id => `{columns, rows}`, `{state: 'refused'}` or null (not read). Each table is asked once a drawing.

## When does a data layer wait for a browser?
- No `$data` reader given (error `data`): every caller today, as only the caller knows whose rights the picture is read with. A channel's card draws its data layers in the reader's browser instead (DV4, `workspace/canvas-data-follow.md`).
- The host draws no text, or the layer carries an unknown field (error `data`).
- A face the server has no file of (error `font`): the guide's labels are weight 600 and the bundled Roboto has no 600, so the default `g:Inter` or any family needs its 400, 600 and 700 files in `data/fonts` (the editor's font folder). A letter outside `pg_image_draw_text_plain()` waits too.

## How is it painted?
- `pg_image_draw_data_item()` reads the colours and places each text's letters from the font's widths at its anchor (start, middle, end), as `fillText()` sets a line. `pg_image_draw_data_draws()` paints in mark order, a run of shapes in one drawing and a run of texts in another, on a tile up to four times larger (`pg_image_draw_group()`, fine) faded by the layer's opacity.
- Lines take the renderer's half-pixel-thinner stroke; filled outlines are not drawn a quarter pixel inside as drawn shapes are. Not compared per pixel with Chromium: the test host has no Imagick.

## How are the rows read?
- `ws_canvas_data_rows($viewer, $table_id)` in `includes/workspace/canvas_tables.php`: `ws_wtable_for(…, 'view')`, else `{state: 'refused'}` (the layer then says "You cannot read this table."); the rows with formulas, lookups and rollups worked out for the reader (`ws_wtable_joined_fill()`), 5000 at most; shaped by `ws_canvas_data_rows_shape()` as `{columns: {id: {name}}, rows: [{cells}]}`. It writes nothing.
- Each cell is handed as the table's screen shows it (`ws_canvas_data_cell()`, #11353: money as its figure, not minor units; a status by label; a person or row link by name, `ws_canvas_data_people()`; text, number, formula, date and list as stored), what `editor.dataRead()` and the server's reader take.
- Whether a canvas may name the table (its `ws_canvas_tables` binding) is the caller's check. The editor's action `data_rows` hands the rows to the editor (`ui/image-editor-data-menu.md`); A channel's card reads them through `canvas_data` (`workspace/canvas-data-follow.md`).
