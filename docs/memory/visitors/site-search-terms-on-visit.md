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
