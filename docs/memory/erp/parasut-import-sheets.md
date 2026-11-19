---
name: parasut-import-sheets
description: Paraşüt's two import sheets written from code by pg_xlsx_write_layout(): the head as data, the column formats, what the old template files had that the new file has not
module: parasut_sheet
---

## Who writes a Paraşüt sheet?
- Orders > Export for Paraşüt Selected (`edit_orders.php`): both sheets in one zip. The ERP export's Paraşüt profiles (`includes/erp/export.php`, `erp_export_write_xlsx()`): one sheet each.
- Both through `pg_xlsx_write_layout()` (`includes/fn/xlsx_write.php`) with `pg_parasut_sheet_layout('invoices' | 'contacts')` (`includes/fn/parasut_sheet.php`). Before #PRNUM the orders export filled `includes/phpexcel/templates/*.xlsx` with PHPExcel and the ERP export rewrote the same files' worksheet as a zip.

## What is the head?
- Row 1 Paraşüt's help note (merged A1:F1 invoices, A1:E1 contacts, with the template's row height), row 2 empty, row 3 the headings in Paraşüt's Turkish words; data from row 4 (`PG_PARASUT_SHEET_FIRST_ROW`). The column widths are the templates'. The texts stay Turkish in any panel language: they are Paraşüt's, not ours.
- Sheet names: `Satış Faturaları`, `Musteri ve Tedarikciler`.

## How is a data cell written?
- A data row is keyed by column index (`pg_parasut_sheet_rows()` turns the orders export's row number => column letter cells into rows). An int or float is a number, `array('date' => 'Y-m-d H:i')` a date serial, anything else inline text, never a formula. Every column of a data row gets a thin grey (B8B8B8) bordered, left-aligned cell, empty or not.
- The orders export's formats: C and F `d.mm.yyyy`, P, Q and S `#,##0.00`, R `#,##` (as PHPExcel wrote them; R shows 0 as blank, the value is still 0). The ERP export sets none: its dates are `d.m.Y` text and its amounts plain numbers, as before.

## What did the template files have that the new file has not?
- Rich text in the contacts note (bold 12 title), the `mailto:destek@parasut.com` link on A1, empty styled rows down to 982, document properties (creator and title) and printer settings. Paraşüt's importer reads the cells from row 4 under the headings; none of these is read.

## How is it tested?
- `tests/parasut_sheet_xlsx_test.php` writes the same input both ways (PHPExcel filling the templates as the orders export did; the template zip as the ERP export did) and compares every cell read back by PHPExcel: value, type and number format. It skips without `includes/phpexcel`; the plan's removal step drops that part.
