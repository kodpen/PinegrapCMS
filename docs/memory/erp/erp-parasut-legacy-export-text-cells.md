---
name: erp-parasut-legacy-export-text-cells
description: Orders > Export for Paraşüt writes typed text (names, e-mail, address, identity number, product name) as text cells, never a formula, through the shared xlsx writer.
---

## What does the export write as text?

`edit_orders.php`, case `export_orders_for_parasut`, fills the cells of the
two Paraşüt sheets as arrays and hands them to `pg_xlsx_write_layout()`
(`parasut-import-sheets.md`). Every typed text column is cast to a string:
contacts sheet A (billing name), D (e-mail), F (address), G (district),
H (city), I/J (phone, fax), M (identity number); invoice sheet A (billing
name), B (organization – order number) and L (product name). The writer
writes every string as an inline text cell and never a formula.

## Why does it matter?

PHPExcel, which wrote the sheets before #PRNUM, typed a string starting with
`=` as a formula and the error words (`#N/A` …) as an error through its
default binder. A checkout name like `=HYPERLINK("http://x.test","a")` was
saved as a live formula and reached Paraşüt as its result; explicit text
cells fixed it then, and the writer keeps it so. Nothing is prefixed: this is
not a CSV apostrophe guard. A number-like identity number (`00012345678`)
stays text and keeps its zeros.

## Where is it pinned?

`tests/parasut_export_cells_test.php` scans the export block for the string
casts and the writer calls; `tests/parasut_sheet_xlsx_test.php` checks that a
`=…` name is written as text, never `<f>`.
