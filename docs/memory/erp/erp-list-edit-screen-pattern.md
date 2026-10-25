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

## How is a list filtered?
- With a select in `#button_bar`: `pg_filter_select($name, $options, $current, $opts)` (docs/memory/ui/page-button-bar.md), each option with its count in the text ("Open (3)"); other query values (the search) ride along through `keep`. No button-group filters.
- Before a new label key, read its value in `includes/local/tr/`: `Net` is "KDV Hariç", `From` / `To` are "Şundan" / "Şuna" (erp-translation-traps.md).

Related: docs/memory/erp/erp-screen-status-column-pattern.md
