---
name: sheet-reader-leading-zeros
description: Spreadsheet import keeps the leading zeros a number format shows (phone, post code, tax ID under 00000000000): which .xlsx, .ods, .xls formats count, what stays (#10622)
module: sheet_read
---

## What was wrong?
- Every importer that takes a workbook (Contacts, Users, Products > Import's .ods/.xls, the bank import, ...) read `05321234567` shown by a custom format `00000000000` as `5321234567`, `06100` as `6100`: the reader gave a number cell its stored value and read the format only for dates (#10622).

## What does the reader give now?
- A whole number (`-?digits`) whose format always shows N digits gets them as leading zeros, as text: `array('t' => 's', 'v' => '06100')`. A number already N digits long, a decimal, a date and every other cell stay as before (type `n`, the file's text).
- Pure helpers in `includes/fn/sheet_read.php`: `pg_sheet_zero_digits($code)` (the N of an Excel format code, 0 when it is not one) and `pg_sheet_zero_pad($cell, $digits)`.
- Fixed text is not added: `"TR"0000` gives `0123`, not `TR0123`, so a unit suffix such as `000" adet"` never reaches the value.

## Which Excel formats count (.xlsx, .xls)?
- Only zeros, at least two, after quoted text (`"TR"`), escaped characters (`\-`) and the dash, space and brackets are taken out: `00000`, `00000-0000` (9), `000-00-0000`. Not: `0`, `0.00`, `#,##0`, sections (`00000;-00000`), a colour or condition (`[Red]0000`), percent, exponent, dates.
- .xlsx: custom `numFmt` codes through the cell's `cellXfs` index (`pg_sheet_xlsx_zero_styles()`, `xl/styles.xml`, its DOM ceiling). .xls: the FORMAT record through the cell's XF (`Pg_Sheet_Xls_Reader::pg_xf_zero_digits()`). No built-in format id is one.

## Which .ods styles count?
- A `number:number-style` in `content.xml`'s automatic styles with one `number:number` whose `number:min-integer-digits` is 2 or more, no decimals, no grouping, no `style:map`; `number:text` around it is fixed text (`pg_sheet_ods_zero_digits()`). Styles only in `styles.xml` are not read.
- Only `office:value-type="float"` cells. The style is the cell's `table:style-name`, else its row's, else its column's `table:default-cell-style-name` (LibreOffice puts a whole formatted column's style on the column).

## And Products > Import's own .xlsx walk?
- `pg_products_import_xlsx_to_csv()` (`product-import-sheets.md`) pads the same way: `pg_sheet_xlsx_zero_styles()` on the styles part its relationships name, a barcode `0000000012345` kept.

## How is it tested?
- `tests/sheet_read_test.php` (format codes, a .xlsx and an .ods built in the test), `tests/sheet_read_xls_test.php` (an .xls with a FORMAT record), `tests/products_import_sheet_test.php` (the product walk).
