---
name: widget-pages-index
description: How pg_sw_widget_pages() finds the pages carrying a system widget type: one read of the page trees per request into a sharedId map instead of a LIKE scan per component (#4459)
module: widgets
---

## Why was it changed?
- `pg_sw_widget_pages()` (`includes/fn/widgets.php`) scanned every page tree with a `LIKE '%"sharedId":N,%' OR … '%"sharedId":N}%'` pair per shared component of the type; no index helps such a pattern. sitemap.xml asks for about twenty types: a draw took 6-7 s on a site with 926 components and 606 pages (#4459).

## How does it work now?
- `pg_sw_widget_page_index()` reads once per request: the pages outside the recycle bin ordered by title then name, then their trees (`pg_page_tree_sql_expr()`) in chunks of 100 by page id, only trees naming a `sharedId`, into `sharedId → page positions`. The tree text is dropped after each chunk.
- Each call still asks `shared_components` for the type's components (and applies `$test`), then `pg_sw_pick_widget_pages()` returns each page once in the listing order. Output is the same as the old query.
- `pg_sw_tree_shared_ids()` matches what the LIKE pair matched: `"sharedId":N` followed by `,` or `}`, on any node, case ignored like the column collation, no space or quotes. Tested in `tests/sw_widget_pages_test.php`.

## What is the catch?
- The index is kept for the request, like `pg_sw_page_widgets()`: a request that saves a page tree and asks afterwards in the same request sees the tree from before the save. Today's callers are page renders, the sitemap, feeds and panel screens that do not write trees first.
