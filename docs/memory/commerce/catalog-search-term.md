---
name: catalog-search-term
description: Which search term a legacy catalog page uses (Clear button, page query, general ?query=, custom layouts) and why its RSS link asks the same helper
---

## Where is a catalog page's search term read?
- `pg_catalog_search_term($page_id, $layout_type)` in `includes/fn/content.php`. It returns the trimmed term, or `''` when the catalog shows its product group.
- `get_catalog.php` searches for it in both layouts; `get_page_content.php` (`case 'catalog':`) shows the page's RSS `<link>` only when it returns `''`, with `get_layout_type()` of the page.

## What does it read?
- System layout: `<id>_simple_clear` (the simple search's Clear button) means no term; otherwise `<id>_query`; only when that is not given at all, the general `?query=` of older links. An empty `<id>_query` beside `?query=x` is no term.
- Custom layout: `<id>_clear` (its form's Clear) means no term; otherwise `<id>_query` from `$_REQUEST`, as the layout's liveform reads it. The general `?query=` is not read.
- A value sent as a list (`?query[]=x`) counts as not given.

## Why one helper?
- The RSS condition used to re-read `$_GET` on its own. After Clear (`?<id>_query=x&<id>_simple_clear=` / `&<id>_clear=`), with `?<id>_query=&query=x`, and with `?query=x` on a custom layout, the catalog showed the product group but the RSS link was missing. Same answer now (PR #477; blank and list queries were PR #419).
- A new reader of the catalog term (title, canonical, structured data) should call the helper rather than read the request.

## Which test covers it?
- `tests/catalog_search_term_test.php`.
