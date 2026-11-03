---
name: list-page-state
description: A paged panel list (table.chart) comes back on its page, length and search after Back (history.state, pgListState/pgListKeep); the pager landmark named from the DataTables language
---

## Why did Back open every list on page 1?
- Panel pages are sent `no-store` (PHP's default session cache limiter), so the browser keeps no back/forward copy and Back, or the strip's `history.go(-1)` arrow, loads the screen again. `stateSave` is off and nothing else kept the page, the length or the search, so the list opened on page 1 with 100 rows and an empty search box. The sort already survived through `pg_list_state()` in the session.

## How is the place kept now?
- `backend.src.js`: on every `draw.dt` of the list, `pgListKeep(key, {start, length, search})` merges it into `history.state.pgList[key]` with `replaceState()` (key: the table's id, else `'0'`). `draw.dt` rather than `page`/`length`/`search.dt`: by then DataTables has settled the start (a new search goes to page 1).
- Before the init, `pgListState(key)` reads it back into `displayStart`, `pageLength` (only a length in `lengthMenu`) and `search.search`. A server-paged list (`server-paged-lists.md`) asks for that start in its first request.
- Only when the screen has exactly one paged `table.chart`: every list shares one `options` object.
- A fresh visit from the menu has no `history.state` and opens on page 1.

## What traps does it rely on?
- The first draw opens on the kept page through `displayStart`; `fnInitComplete` draws no second time. A bare `draw()` would set `_iDisplayStart = 0` (`_fnReDraw`, DataTables 2.3.7) and throw the restored page away.
- `fnInitComplete` shows the hidden table first, then calls `columns.adjust()` once (#6072): the earlier adjust on the hidden table, a second adjust and a `draw(false)` froze the Pages list ~440 ms more on open. No `$(window).on('resize')` adjust either (#6074, ~330 ms per event): with `scrollX` DataTables watches its wrapper width with a throttled `ResizeObserver`. Pinned by `tests/list_init_measure_test.php`.
- The `?query=` handler keeps `history.state` in its `replaceState()`; `null` wiped the kept state. `view_folders.php` still replaces it with `null` on its own lists.
- Never switch on DataTables `stateSave`: it also keeps ColReorder order and column visibility, which the Reset button expects to start fresh.

## How is the pager named?
- DataTables 2.3.7 hard-codes `<nav aria-label="pagination">` (`datatables.js`, not edited). After init the nav takes `datatable.i18n('aria.paging', 'Pagination')`; `assets/lib/DataTables/i18n/tr.json` holds `aria.paging` "Sayfalama". A new panel language adds the key to its file.

## Where is it pinned?
- `tests/list_page_state_test.php` (source scan), with the Ctrl+K Load More focus (`command-palette.md`).

Related: docs/memory/ui/list-and-table-screens.md, docs/memory/ui/server-paged-lists.md
