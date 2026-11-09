---
name: workflow-tables-export
description: A workflow table downloads as an Excel file (TX1): who may, what each cell becomes, the shared xlsx writer the ERP export also uses
module: xlsx_write
---

## What does it do?
- Since #8408 the table screen's toolbar has Download as Excel for whoever may view the table (`ws_wtable_for()`, view): `workspace_wtables.php?id=<n>&download=xlsx`, a GET, no token (it changes nothing).
- One sheet named after the table (`pg_xlsx_sheet_title()`: no `[]*?/\:`, 31 letters), a bold frozen heading row, every row in the table's order (`ws_wtable_rows()`, the 5000-row limit). Before it a table left the Workspace only by copying cells.
- The file name is the table's name in ASCII (`ws_wtable_export_file_name()`, Turkish letters folded), `table-<id>.xlsx` when nothing is left.

## What does each cell become?
- `ws_wtable_export_cell()` beside `ws_wtable_screen_cell_text()` in `includes/workspace/wtable_screen.php`, pure, tested in `tests/workspace_wtable_export_test.php`.
- Number and formula: a number (text the store did not check stays text); a failed formula `#<CODE>` as the screen shows it.
- Money: its figure as a number (minor units over the column's decimals); the currency goes to the heading, `Price (EUR)` (`ws_wtable_export_sheet()`).
- Date: a real date cell (style 2, or 3 with a time), so it sorts; a value that is no date stays text.
- Person: the name; status: the label; checkbox: `lang('Yes')` / `lang('No')`; record link and text: what the screen shows.

## Where is the workbook written?
- `includes/fn/xlsx_write.php` (module `xlsx_write`, loaded by `functions.php`): `pg_xlsx_write_plain()`, `pg_xlsx_sheet_rows()`, `pg_xlsx_date_serial()`, `pg_xlsx_column_letter()`, `pg_xlsx_xml()`. Made from the ERP export's writer, which could not be shared as `includes/erp/export.php` stops without `PG_ERP_ENTRY`.
- `erp_export_write_plain_xlsx()`, `erp_export_sheet_rows()`, `erp_export_column_letter()` and `erp_export_xml()` stay as thin wrappers (the accountant pack calls them); the styles sheet gained the two date formats only.
- A cell value `array('date' => 'Y-m-d' | 'Y-m-d H:i')` is a date; int/float a number; anything else inline text; '' and null leave the cell out.
- The download writes to a `tempnam()` file under `data/temp`, sends it and deletes it.

## What is left?
- A download of what the screen's filter shows, a CSV download, an export route in the API (`plans/tables-xlsx-import-route.md`, later).
