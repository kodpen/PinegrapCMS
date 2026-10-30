---
name: erp-parasut-legacy-export-text-cells
description: Orders > Export for Paraşüt writes typed text (names, e-mail, address, identity number, product name) as explicit text cells, never through PHPExcel's default binder.
---

## What does the export write as text?

`edit_orders.php`, case `export_orders_for_parasut`, fills the two Paraşüt
workbooks. Every column that holds typed text uses
`setCellValueExplicit(..., PHPExcel_Cell_DataType::TYPE_STRING)`: contacts
sheet A (billing name), D (e-mail), F (address), G (district), H (city),
I/J (phone, fax), M (identity number); invoice sheet A (billing name),
B (organization – order number) and L (product name). Only fixed labels,
dates (C, F) and numbers (O–S) go through `setCellValue()`.

## Why not setCellValue()?

The vendored `PHPExcel_Cell_DefaultValueBinder::dataTypeForValue()` types a
string starting with `=` (longer than one character) as a formula and the
error words (`#N/A` …) as an error. A checkout name like
`=HYPERLINK("http://x.test","a")` was saved as a live formula, ran when the
accountant opened the file and reached Paraşüt as a formula result. Explicit
text cells keep the value exactly as typed; ordinary names give the same file.
This is not a CSV apostrophe guard: nothing is prefixed.

## Where is it pinned?

`tests/parasut_export_cells_test.php` scans the export block: the text
columns are explicit, and a plain `setCellValue()` only takes the fixed,
date and number columns (L only for the `lang()` fee labels).
