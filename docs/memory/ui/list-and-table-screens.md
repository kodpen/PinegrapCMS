---
name: list-and-table-screens
description: Panel list and table rules: lists on the list table driver, which lists must stay plain tables, no form inside a table row, card-prefixed variables in long screen files, unclosed divs.
---

## How is a list screen drawn?
- A list is drawn by the list table driver (`pg_list_table_html()`, `list-table-driver.md`; a small list printed whole: `list-table-printed.md`). DataTables and `table.chart` are gone from the panel (#8253); a new list never brings them back.
- Adding and editing happen on separate pages (`add_*.php` / `edit_*.php`), not inside the list.

## How are list cells and icons made safe and named?
- A preview of stored markup (login region headers and footers) is cut to 50 characters first, then escaped with `h()` in its cell; it once printed short previews raw, so a stored `<h3>` or `<style>` rendered in the list.
- A Material ligature that stands for a value says it: a `task_alt` check mark is `role="img" aria-label` Yes, the pages list's `home` mark Home Page; a decorative one beside a name (`folder`) is `aria-hidden="true"`.
- A list on the driver has no row checkbox (owner 2026-11-03, `workflow/decisions-made-product-22.md`): a click, Ctrl, Shift or Space selects the row (`list-table-driver-browser.md`).
- File lists: `download` holds the file's name (it once held the type, `jpg`); thumbnails keep 50x50 with `object-fit: cover`. Pinned by `tests/content_lists_test.php`.

## Which lists must stay plain tables?
- Lists whose order is their meaning (account statement, cash ledger, invoice lines): a running balance is right only in date order, and a line number is the document itself. Draw them as a plain `table table-hover` (`erp/erp-list-edit-screen-pattern.md`).
- A table whose rows hold form fields is never a paged list: paging, a search and a hidden column take rows and cells out of the DOM, so the form posts only what is on screen. Edit Featured & New Items (`edit_featured_and_new_items.php`) was a `table.chart`, and a save after a search cleared Featured and New on every item it hid; it is a plain table now, pinned by `tests/featured_new_items_test.php`.
- Such a table can still sort by its headings: `table[data-pg-sort]` (`backend.src.js`; cell `data-sort`, fixed and paired rows, a heading opts out with `data-pg-sort="none"`): `erp/erp-list-edit-screen-pattern.md`.

## May a table row hold a form?
- Never. The table usually sits inside the page's `<form>`; nested forms are invalid and the parser drops the inner one, so its button submits the outer form. Row buttons are `type="button"` with `data-*`; they post one hidden form that is a SIBLING of the page form, through a listener delegated to `document` (`commerce/order-cancellation-flow.md`).

## What breaks silently in long screen files?
- A new card reusing another card's variable names: the `edit_erp_invoice.php` reminder card reused `$lines` and emptied the invoice lines. Give a card's variables its own prefix (`$reminder_lines`).
- A card that leaves a `<div>` open swallows every card after it ("cards stuck together", "Save hanging"); count `<div` against `</div>` per card (`settings-card-save-rules.md`).

Related: docs/memory/ui/admin-screen-toolbar.md, docs/memory/ui/list-table-driver.md
