---
name: client-list-turkish-search
description: Turkish search of DataTables lists searched in the browser (orders, pages, styles, menus): which lists, the rule, why a matcher, how the list state keeps the typed text
---

## Which lists does it cover?
- Every `table.chart` without `data-pg-list-action` whose search box is on (not `datatable-restricted-mode`), on a panel whose `software_system_language` starts with `tr`: orders (`view_orders.php`), pages, styles, menus, calendars and the rest. Lists paged on the server search in SQL (`server-paged-lists.md`, PR #3677) and are skipped (`options.serverSide`). Other panel languages keep DataTables' own search unchanged.

## What does a search word find?
- `pgListTurkishMatch(term, text)` in `backend.src.js` (pure; tests in `tests/list_turkish_client_search_test.php` under Node). The words are DataTables' smart words (any order, a "quoted phrase" is one word, `!word` must be missing). A word is found when DataTables' own match finds it (case-insensitive, marks stripped: IPHONE→iPhone, DEMİR→Demir) or when it occurs folded (`pgListTurkishFold()`: `window.pgFoldTR`, so I, İ, ı and i are one letter, then marks stripped): YILDIZ and yildiz→Yıldız, YALÇIN→Yalçın, isik→Işık, ismail→İsmail.
- The fold of I, İ, ı and i was decided on issue #3742 and built in PR #3763 (same rule as the server lists and `ws_fold()`).

## Why a matcher and not a diacritics hook?
- DataTables tests a string search with a case-insensitive RegExp, and JavaScript never pairs I with ı. A `DataTable.util.diacritics()` hook lowers both sides the same way, so lowering IPHONE the Turkish way (ıphone) would lose iPhone; the rule needs "either match", which only a function search gives. `ext.search` and `search.fixed` are ANDed after the global search, so they cannot add rows back.

## How does the box and the kept state still hold the text?
- `pgListTurkishSearch(datatable)` replaces `settings.oPreviousSearch.search` with a getter/setter: the setter keeps the typed string (box input, `datatable.search(str)`, the `?query=` path, the restored state all assign it), the getter hands DataTables' filter a matcher whose `toString()` is the typed text (DataTables' box compares with `!=`). `pgListKeep()` stores `String(datatable.search())`, so `pgListState()` still reads a string.
- DataTables empties its box for a function search; a `search.dt` handler puts the text back on the next tick unless the box has focus.

## Where was it done?
- PR #3751 (part of issue #3584; server lists were #3677). Checked headless against `datatables.js` 2.3.7, not on a panel.
