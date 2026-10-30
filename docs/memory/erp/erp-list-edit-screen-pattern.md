---
name: erp-list-edit-screen-pattern
description: How ERP list and add/edit screens are built (table.chart, separate edit pages, liveform fields, menu highlight), which lists must not be DataTables and how they sort, list filters.
---

## How is an ERP list drawn?
- Like a `view_*` screen: `<table class="chart table-hover table" style="width:100%;display:none;">`. `backend.src.js` sets up DataTables automatically (search, paging, column visibility, sorting; `fnInitComplete` removes `display:none`).
- First column `<th class="noVis">Eylem</th>` with a pencil button to `edit_*.php?id=`.

## How are add/edit pages built?
- Separate pages: `add_erp_account.php` / `edit_erp_account.php`, `add_erp_till.php` / `edit_erp_till.php`, `add_erp_invoice.php` / `edit_erp_invoice.php`, `add_erp_receipt.php`, `add_erp_transfer.php`.
- Shape: `if (!$_POST) { form } else { validate_token_field(); save; go(); }`.
- Fields via `$liveform->output_field()` (error marking and refill come from it); never hand-write `<input>`.
- Fields shared by two pages: `includes/erp/account_form.php`, `includes/erp/till_form.php`.
- A new page must be added to the `$active_menu = 22` switch in `includes/fn/output.php`, or the menu highlight is lost.

## Which lists are not DataTables?
- Lists where order carries meaning: account statement, cash ledger, invoice lines. A running balance is only correct in date order and a line number is the document itself. Draw them as plain `table table-hover`.
- Sorting by a heading on such a table: `table[data-pg-sort]` (`backend.src.js`; a third click restores the server's order, Shift+click adds a level, the choice is remembered per page and table). A cell sorts by `data-sort` (date as `Ymd`, amount in kurus), else `data-order`, else its text. An opening or total row is `data-pg-sort-fixed` (`="top"` keeps it on top); a row that belongs to the one above is `data-pg-sort-with-prev`; a heading that must not sort is `data-pg-sort="none"`. Never on a form grid (the invoice editor's lines).

## How does a figure table name its figures?
- A table of several columns has a `<thead>`; in a drawer or small card it is `<tr class="fg-secondary"><th class="fw-normal">` (the invoice drawer's lines). The account drawer movements (Date, Description, Amount, Balance), the waybill drawer lines and the cheque Movements had none, so the amount and the running balance were told apart only by a grey tint.
- A label/amount statement (profit, VAT statement and aside, expense figures) and the cash forecast's kind and total rows label each row with `<th scope="row">`: `fw-normal` on a plain row, the row's own `fw-semibold`, nothing on a `fw-bold` row (a `th` is bold, so `fw-normal` would undo it). The forecast corner is `lang('Type')`.
- VAT By rate prints the document type on every row; the repeats sit in a `visually-hidden` span, so no row reads with an empty type.
- Tables whose other rows keep their first column a `td` under a named heading (opening / closing balance, month totals) stay as they are. Test: `tests/erp_table_headers_test.php`.

## How is a list filtered?
- With a select in `#button_bar`: `pg_filter_select($name, $options, $current, $opts)` (docs/memory/ui/page-button-bar.md), each option with its count in the text ("Open (3)"); other query values (the search) ride along through `keep`. No button-group filters.
- Before a new label key, read its value in `includes/local/tr/`: `Net` is "KDV Hariç", `From` / `To` are "Şundan" / "Şuna" (erp-translation-traps.md).

Related: docs/memory/erp/erp-screen-status-column-pattern.md
