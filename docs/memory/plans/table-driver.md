---
name: table-driver
description: Plan (owner 2026-11-03): the panel's own list table driver replacing DataTables, and the move of every table.chart screen to it, screen by screen (TD1-TD14)
---

## What is this plan?
- DataTables 2.3.7 (578 KB with JSZip and extensions the panel never configures) loads on every panel page. The panel uses paging, search, column visibility and order, the sticky header and server paging; its redraw cleared every ticked row (`drawCallback`, `backend.src.js`), so a bulk selection did not survive paging or rows fetched step by step.
- The owner's behaviour for the replacement is in `workflow/decisions-made-product-22.md`; this plan builds it and moves the screens.

## Where does the code live?
- Server: `includes/fn/list_table.php` (column model, the first rows, the rows answer, the selection resolver), the rows action beside today's in `includes/panel/lists.php`, reusing `list_paging.php` (search, sort state, row cells).
- Browser: `assets/js/pg_list_table.js` in a bundle (`pg_js_bundles()`, `tools/build_js.php`), styles in `backend.src.css`. `includes/` is closed to the web (`web.config`), so no script lives there.

## What does the driver do?
- Rows: the first 100 printed with the page, then the rows action per 100 (keyset, the total counted once per condition), an IntersectionObserver plus "Daha fazla göster". A browser mode for small lists that print every row: client sort with Turkish collation and the search of `pgListTurkishMatch()`.
- Selection and its bar, Ctrl+A by condition with exceptions, the undo of a replaced selection, sort without a reload, the fill-height layout with a CSS sticky header, the columns menu and the details panel kept in localStorage (`pg.list.<user id>.<list>`), phone cards below 768 px, the quick edit mode over today's `pg_list_edit_request()` endpoints, thumbnails through `pg_image_panel_thumb_url()` at most 6 in flight.
- Bootstrap 6 markup, `lang()` texts, roles `table`/`row`/`cell`, `aria-sort`, a live region for loaded rows and saves.

## Which decisions bind it?
- Plain tables stay plain: lists whose order is their meaning and tables holding form fields keep `table[data-pg-sort]` (`ui/list-and-table-screens.md`).
- Until a screen's step lands it stays on DataTables and its fixes go on (`decisions-made-product-22.md`).
- About 1,000 product lines per step; texts through `tr_add.php`; each step writes its `ui/` memory file and tests; claim as `[plan:table-driver] TD<n>`. Steps and the screen lists: `table-driver-steps.md`, `table-driver-steps-2.md`.

## Open questions?
- None.

## Where to start?
- A step opens when the steps its line names after have landed (`table-driver-steps.md`, `table-driver-steps-2.md`).
