---
name: client-list-turkish-search
description: Turkish search of lists searched in the browser (the list table driver's browser mode): the rule of pgListTurkishMatch(), the fold of I, İ, ı and i
---

## Which lists does it cover?
- Every list the list table driver prints whole and searches in the browser (browser mode, `list-table-printed.md`): `pg_list_table.js` calls `window.pgListTurkishMatch` when it is there. Lists paged on the server search in SQL with the same fold (`server-paged-lists.md`, PR #3677).

## What does a search word find?
- `pgListTurkishMatch(term, text)` in `backend.src.js` (pure; tests in `tests/list_turkish_client_search_test.php` under Node). Words in any order, a "quoted phrase" is one word, `!word` must be missing. A word is found by a case-insensitive match with marks stripped (IPHONE→iPhone, DEMİR→Demir) or when it occurs folded (`pgListTurkishFold()`: `window.pgFoldTR`, so I, İ, ı and i are one letter, then marks stripped): YILDIZ and yildiz→Yıldız, YALÇIN→Yalçın, isik→Işık, ismail→İsmail.
- The fold of I, İ, ı and i was decided on issue #3742 and built in PR #3763 (same rule as the server lists and `ws_fold()`).

## Why "either match"?
- JavaScript's case-insensitive RegExp never pairs I with ı, and lowering IPHONE the Turkish way (ıphone) would lose iPhone; so a word counts when either the plain or the folded match finds it.

## Where was it done?
- PR #3751 (issue #3584) on DataTables' search; its DataTables installer went with the removal of DataTables (TD14b2, #8253), the matcher stayed for the driver.
