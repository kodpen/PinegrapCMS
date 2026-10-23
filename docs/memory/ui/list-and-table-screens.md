---
name: list-and-table-screens
description: Panel list and table rules: the DataTables list markup, which lists must stay plain tables, no form inside a table row, card-prefixed variables in long screen files, unclosed divs.
---

## How is a list screen drawn?
- `<table class="chart table-hover table" style="width:100%;display:none;">`: `backend.src.js` turns every `table.chart` into a DataTable (search, paging, column visibility) and shows it when it is ready. The action column comes first as `<th class="noVis">` with `lang('Action')`; `noVis` keeps a column out of the column picker.
- Adding and editing happen on separate pages (`add_*.php` / `edit_*.php`), not inside the list.

## Which lists must not be DataTables?
- Lists whose order is their meaning (account statement, cash ledger, invoice lines): a running balance is right only in date order, and a line number is the document itself. Draw them as a plain `table table-hover` (`erp/erp-list-edit-screen-pattern.md`).
- Such a table can still sort by its headings: `table[data-pg-sort]` (`backend.src.js`; cell `data-sort`, fixed and paired rows, a heading opts out with `data-pg-sort="none"`): `erp/erp-list-edit-screen-pattern.md`.

## May a table row hold a form?
- Never. The table usually sits inside the page's `<form>`; nested forms are invalid and the parser drops the inner one, so its button submits the outer form. Row buttons are `type="button"` with `data-*`; they post one hidden form that is a SIBLING of the page form, through a listener delegated to `document` (`commerce/order-cancellation-flow.md`).

## What breaks silently in long screen files?
- A new card reusing another card's variable names: the `edit_erp_invoice.php` reminder card reused `$lines` and emptied the invoice lines. Give a card's variables its own prefix (`$reminder_lines`).
- A card that leaves a `<div>` open swallows every card after it ("cards stuck together", "Save hanging"); count `<div` against `</div>` per card (`settings-card-save-rules.md`).

Related: docs/memory/ui/admin-screen-toolbar.md
