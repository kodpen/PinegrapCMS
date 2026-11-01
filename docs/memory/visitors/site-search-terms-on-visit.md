---
name: site-search-terms-on-visit
description: Who writes visitors.site_search_terms, and why the search widget writes it only on the first results page
module: site_search
---

## Who writes `visitors.site_search_terms`?
Only `pg_search_record_visit_term($term)` (`includes/fn/site_search.php`).
Both search screens call it: the search widget of designed pages
(`_render_system_widget_search_results()`) and the legacy Search Results
page (`get_search_results.php`). It does nothing for an empty term, with
visitor tracking off, or when the session's visit row is gone.

## What format?
Terms joined with `|` (`pg_search_visit_terms_append()`, pure), as the
visitor report's summary/filter, the visit detail and the API's
`site_search_terms` breakdown split them.

## Why did designed sites show no search terms?
Until #4353 only the legacy page wrote the column, so on sites built with
the visual designer the "Site Search Terms" report and visit detail were
always empty.

## Does paging add the term again?
The widget writes only when the request carries no page parameter
(`pg_sw_list_page_param()`), so moving to page 2 of the same search does
not add it twice. The legacy page still writes on every request with a
query, as before.

## Why does a long search history stop growing?
The column is `varchar(255)` and the session's `sql_mode` is not strict, so
MySQL used to cut a longer value: half a term was stored and every later
search of the visit was lost. `pg_search_visit_terms_append()` now keeps the
value within 255 characters: a lone term is cut to it, a term that does not
fit after the earlier ones is not added (the visit's `stop_timestamp` is still
written), and a `|` typed in a term becomes a space so it stays one term.
Test: `tests/site_search_visit_terms_test.php`.
