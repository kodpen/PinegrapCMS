---
name: list-and-table-screens
description: Panel list and table rules: the DataTables list markup, which lists must stay plain tables, no form inside a table row, card-prefixed variables in long screen files, unclosed divs.
---

## How is a list screen drawn?
- `<table class="chart table-hover table" style="width:100%;display:none;">`: `backend.src.js` turns every `table.chart` into a DataTable (search, paging, column visibility) and shows it when it is ready. The action column comes first as `<th class="noVis">` with `lang('Action')`; `noVis` keeps a column out of the column picker.
- Adding and editing happen on separate pages (`add_*.php` / `edit_*.php`), not inside the list.

## How are list cells and icons made safe and named?
- A preview of stored markup (login region headers and footers) is cut to 50 characters first, then escaped with `h()` in its cell; it once printed short previews raw, so a stored `<h3>` or `<style>` rendered in the list.
- A Material ligature that stands for a value says it: a `task_alt` check mark is `role="img" aria-label` Yes, the pages list's `home` mark Home Page; a decorative one beside a name (`folder`) is `aria-hidden="true"`. A row checkbox carries `aria-label` "Select {var:1}" with the row's name.
- File lists: `download` holds the file's name (it once held the type, `jpg`); thumbnails keep 50x50 with `object-fit: cover`. Pinned by `tests/content_lists_test.php`.

## Which lists must not be DataTables?
- Lists whose order is their meaning (account statement, cash ledger, invoice lines): a running balance is right only in date order, and a line number is the document itself. Draw them as a plain `table table-hover` (`erp/erp-list-edit-screen-pattern.md`).
- A table whose rows hold form fields is never a paged DataTable: DataTables takes rows on other pages, rows the search hides and cells of a hidden column out of the DOM, so the form posts only what is on screen. Edit Featured & New Items (`edit_featured_and_new_items.php`) was a `table.chart`, and a save after a search cleared Featured and New on every item it hid; it is a plain table now, pinned by `tests/featured_new_items_test.php`.
- Such a table can still sort by its headings: `table[data-pg-sort]` (`backend.src.js`; cell `data-sort`, fixed and paired rows, a heading opts out with `data-pg-sort="none"`): `erp/erp-list-edit-screen-pattern.md`.

## Where do DataTables' own texts come from?
- `backend.src.js` loads `assets/lib/DataTables/i18n/<panel language>.json` for the languages in `DATATABLES_LANGUAGES` (`tr`); English uses DataTables' defaults. The file is in DataTables 2 form (`lengthLabels` "-1" for "All", `aria.paginate` for the pagination buttons' names). It replaced the CDN's 1.13.6 `tr.json`, loaded for every panel language, without the 2.x keys, and holding each table back until a third party answered (#2043). A new panel language adds its file and its key there. The CSP still lists `cdn.datatables.net` (`waf.php`).

## May a table row hold a form?
- Never. The table usually sits inside the page's `<form>`; nested forms are invalid and the parser drops the inner one, so its button submits the outer form. Row buttons are `type="button"` with `data-*`; they post one hidden form that is a SIBLING of the page form, through a listener delegated to `document` (`commerce/order-cancellation-flow.md`).

## What breaks silently in long screen files?
- A new card reusing another card's variable names: the `edit_erp_invoice.php` reminder card reused `$lines` and emptied the invoice lines. Give a card's variables its own prefix (`$reminder_lines`).
- A card that leaves a `<div>` open swallows every card after it ("cards stuck together", "Save hanging"); count `<div` against `</div>` per card (`settings-card-save-rules.md`).

Related: docs/memory/ui/admin-screen-toolbar.md, docs/memory/ui/list-page-state.md
