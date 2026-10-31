---
name: command-palette-ranking
description: Panel search box (Ctrl+K) results: the relevance score, Turkish I folding, a fixed per-type cap read best first and the full tie-break that keep Load More pages disjoint
---

## How is a result scored?
- `pg_panel_search_score()` (`includes/panel/search.php`): exact 100, starts with 60, contains 30, plus 15 when the secondary line contains the query. Both sides go through `fold_keyword_for_duplicates()` (I, İ, ı read as i). With `mb_strtolower()` a capital İ became i + U+0307, so typing "iade" scored the "İade" page 0 while "Ödeme ve iade" scored 30, and the exact page could fall behind Load More. Orders score against the query without its leading `#`, and their LIKE goes through `pg_list_like_pattern()` like the rest, so a typed `%` or `_` no longer lists every order. Pinned by `tests/panel_search_score_test.php` (#3650).

## How does Load More page the results?
- Every call (offset 0, 20, 40, …) reads the same candidates: each type at most `PG_PANEL_SEARCH_PER_TYPE` (100) rows, ordered in SQL by exact name, then starts with, then the rest, then the primary key (orders: number rank, then newest). `pg_panel_search_page()` sorts the merged list with `pg_panel_search_compare()` (score desc, a fixed type order, id asc) and slices 20, so two offsets never share a result and together are the top results.
- Before, each type read offset + 21 rows with no ORDER BY: a better match the first call had not read jumped to the top on Load More, the result at the old edge came again and the better one was never shown. Equal scores also reordered, since `usort()` is not stable before PHP 8.0. `appendResults()` in `backend.src.js` does not remove duplicates, so the server must not send any. Pinned by `tests/panel_search_paging_test.php`.

Related: docs/memory/ui/command-palette.md
