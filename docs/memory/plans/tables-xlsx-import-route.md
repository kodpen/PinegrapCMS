---
name: tables-xlsx-import-route
description: Plan (owner 2026-11-09): a workflow table downloads as an Excel file; an API route and MCP tool import a file or a note's table into a table, with a dry run; steps TX1-TX2
---

## What is it for?
- A table leaves the Workspace only by copying cells: there is no download. A file or a note's table comes in on the panel only (`wtable_import.php`, T6a and T6b), so an agent or a script cannot bring one in.
- Decision: `workflow/decisions-made-workspace-10.md`. No schema.

## What exists already?
- Reading: `ws_wtable_import_file_table()` reads CSV, xlsx, xls and ods; `ws_wtable_import_note_read()` reads a table inside a note; `ws_wtable_import_match()` pairs the file's columns with the table's by name; `ws_wtable_import_rows($viewer, $table, $rows, $map)` writes rows; `ws_wtable_import_create()` makes a new table. Nothing answers "what would happen" without writing.
- Writing a workbook: `erp_export_write_plain_xlsx()` writes a heading row and rows with number cells, but its file stops without `PG_ERP_ENTRY`. `pg_products_export_xlsx()` writes every cell as text. `includes/fn/office.php` only reads.
- The screen's cell text: `ws_wtable_screen_cell_text()`. The API gives raw cells (`ws_wtable_row_present()`).

## Which steps are there?
- TX1 (S, no schema) Download as Excel. The table screen's menu offers it to whoever may view the table (`ws_wtable_for()`): one sheet named after the table, a bold heading row, every row in the table's order. Number, money and formula cells are numbers (money as its figure, the currency in the heading), a date is a date, a person a name, a status its label, a checkbox Yes or No, a record link the text the screen shows. A pure presenter beside `ws_wtable_screen_cell_text()`, with a test. The writer becomes a shared one in `includes/fn/` (a new module, named in the memory file's `module:`), made from `erp_export_write_plain_xlsx()` and its helpers, which the ERP export then calls. A GET of `workspace_wtables.php`, no new root file. Memory: `workspace/workflow-tables-export.md`. Claim as `[plan:tables-xlsx-import-route] TX1 — table as Excel`.

- TX2 (M, no schema) Import route. `workspace.tables.import` (POST). The source is a file of the site the caller may read, by its id, or a note with the number of its table. The target is a table by its id, or the name of a new table. The columns pair by name, or by a `map` the caller gives. `dry_run` answers the columns it would make or fill, the row count and every refusal, through a new `ws_wtable_import_check()` that the panel's import uses too (`api_dry_run_stop()`). Rights and limits as on the panel; rows are written quietly, as there. Offered as an MCP tool (`pg_mcp_tool_from_route()`); its schema passes `tools/check_api_schema.php`. Memory: `workspace/workflow-tables-import.md`. Claim as `[plan:tables-xlsx-import-route] TX2 — import route`.

## What is left for later?
- A download of what the screen's filter shows (step 3 of `tables-relational`), a CSV download, an export route in the API.

## Done when?
- Both steps merged; `workspace/workflow-tables-later.md` loses its line about them and this file is deleted.
