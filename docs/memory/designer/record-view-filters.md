---
name: record-view-filters
description: form_item_view filters (opt-in record visibility, e.g. Status = Published): where they apply, fail-closed cases, the e-mail bypass, viewer_filter lists without feed or sitemap (#1194)
---

## Why does a record view have filters?
- A public `form_item_view` showed any record of its form by `?r=` or pretty address: drafts (office-use `status` field) leaked into the page, head tags, `?rss=true` and the sitemap.
- It now takes the list widget's `filters` (same shape and SQL, `pg_sw_form_list_filter_sql()`). Opt-in: no `filters` or an empty list behaves as before.

## Where do the filters apply?
- One check, `pg_sw_record_passes_filters()`: its own `COUNT(*)` (the standard fields' joins use fixed aliases), memoized per request. Strict (`pg_sw_record_filter_clause(..., true)` → `closed`): a failed query, a field the form lacks (`missing`) or an operator outside the list (`invalid`) shows no record; lists skip such rows. Operators are the English constants, never `lang()`.
- Renderer: an outside `?r=` takes the not-found path (404 + noindex).
- `pg_sw_record_comment_context()['visible']`: title, description, OG, canonical, JSON-LD, comments, watchers, `seo.php` breadcrumbs.
- `pg_sw_route_record_address()`: a filtered-out name leaves `?r=` empty; no 301 from a list page to it.
- Feeds: the record page applies its own; a list's feed and the sitemap use `pg_sw_list_record_filter_clause()` (detail page's filters, then the list's).
- Canvas preview and the SEO pass (`pg_seo_widget_record_query()`): the newest record that passes; none → preview shows not-found, SEO skips the page.
- Editor: Filters section in the record-view panel (`_swFormListFiltersHtml()` with a note, same `.sd-sw-filter` handlers). An unknown field or operator shows "(incompatible — choose again)".

## What must the operator do?
- Filters belong to the widget, not the form. Every public list and every other public record view of the form, on any page, needs the same filter: a pretty name resolves on any page whose first record view shows the form, and head/feed read only that widget. E-mail pages can carry it (the bypass below).
- Template specs: check every public `form_item_view` over a filtered form.

## What closes the page?
- Renaming/deleting the filtered field, renaming the option value, or pointing the widget at another form whose fields the rows do not name: every record 404s, feed and sitemap drop them. Rows are kept on a form change (same-named fields stay valid); the panel flags the rest.
- A list switched to a record view keeps `filters` (live, shown in the panel) and `viewer_filter` (inert, `pg_sw_form_widget_public()`).

## What is left out on purpose?
- E-mail bypass only for a server-named record: `email_record_id` in the renderer, `pg_sw_record_filter_bypass($email, $form_id)` in the context. `?r=<draft>&email=true` is still filtered.
- No editor bypass on the public page.
- `complete = 0` records are not hidden (legacy `get_form_item_view.php` shows them); `Complete is equal to Complete` hides them.
- Legacy layer and API unchanged.

## What changed for lists?
- `pg_sw_form_widget_public($cfg)`: public access, no `viewer_filter`. A viewer-filtered list gets no public feed and no sitemap records (legacy parity).

## Where are the tests?
- `tests/record_view_filters_test.php`: pure helpers, invalid operator, no-query cases, the panel. SQL is verified live: `?r=` (+`&email=true`), pretty address, `?rss=true`, `sitemap.xml`.
