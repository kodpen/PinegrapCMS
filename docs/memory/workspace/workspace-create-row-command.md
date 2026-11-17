---
name: workspace-create-row-command
description: /create row (/oluştur satır) in a channel: the table matched by name among those the person may write, the card of the table's fields, the write through ws_wtable_row_create(), rights, strip
---

## What does /create row do? (CC7b)
- `/oluştur satır Adaylar` (also `satir`, `row`): the words after the kind name a workflow table. `ws_command_row_table_match()` takes the table whose whole name they are (case and Turkish letters folded), else the one table whose name starts with them; several or none leave it to the card (architect, 2026-11-17, agent channel message 8138: no new `#` tag kind for tables).
- The answer is `create_preview` with `preview.kind` `wtable_row`, `tables` (the tables the person may write), `table_id` (0 when not matched) and `columns` (`ws_command_row_columns()`).
- The card (`drawRowCreate()` in workspace.js) shows a Table select, then each column the row stores, empty: a status its choices, a person the team, a row link the rows of a table the person reads (else left out), a date its picker, text and list a box, the rest a line. Choosing a table sends the line again with `table_id` and draws its fields. No decision tick and no "All fields": the card is the table screen's own row form.

## How is it written?
- Only Create writes: it sends `confirm`, `table_id` and `cells` (column id => as typed; `ws_send` passes them), each read by `ws_wtable_screen_cell_from_form()` as the table screen reads its form, then `ws_wtable_row_create()`: the table's rights, key, limits, row number, events and flows. A refused cell is named by its column (`ws_command_row_refusal()`).

## Who is offered it?
- Whoever may write a table: `ws_command_row_tables()` keeps the tables of `ws_wtables_reached()` with edit access or more, and `ws_command_create_may()` gives the row kind only then. Nobody else is offered /create for a row, sees `satır` in the kind popup, or is let through ("You may not create this kind of record."). A table outside that list, the card's too, is refused as not found.

## What does the strip show?
- `/oluştur satır <tablo>` with the example `/oluştur satır Adaylar`: the `/create` registry entry's `variants` line (workspace-create-contact-row.md).

## Tests?
- tests/workspace_command_create_row_test.php.
