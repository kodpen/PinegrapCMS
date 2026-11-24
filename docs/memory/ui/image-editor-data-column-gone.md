---
name: image-editor-data-column-gone
description: A data layer whose table lost a column, or every column (#11353): what the layer's panel and the Data picker show
module: image_editor_data
---

## What does the panel show when a column the layer names was deleted?
- The note "A column this layer showed was deleted from the table: pick another." in its Data group (`dataColumnGone`), shown while the label or a value column is not among the table's columns. The panel: `image-editor-data-menu.md`.
- A gone label: Label column opens on a blank first option, so picking any column, the first one too, is a change. Before #11353 the select showed the first column as chosen and picking it did nothing.
- A gone value column is left out of the checkboxes' count and dropped by the next change; before, it stayed hidden in the description and took one of the five places.
- The drawing is not changed: a gone label draws blank labels, a gone value column a series of noughts with no name, the same in the model and the PHP port. Only the panel tells it.

## And a table with no column left?
- "Show on a canvas" and the Data picker's second step say "This table has no columns to show." (`dataNoColumns`) in place of the cards. Before #11353 "Show on a canvas" failed with a script error (no kind's start fits, the Table card was drawn from nothing) and the picker showed an empty box.

## How is it checked?
- `tests/image_editor_data_menu_test.php`: `test_image_editor_data_menu_panel_column_gone`, `test_image_editor_data_menu_picker_no_columns`.
