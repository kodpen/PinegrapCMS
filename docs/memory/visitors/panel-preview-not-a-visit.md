---
name: panel-preview-not-a-visit
description: The File Manager's page previews (preview pane, Quick look) load the page with pg_preview=1; get_page.php opens no visitor record and counts no page view for them
module: seo
---

## Which draws are not counted?
- A page drawn with `pg_preview=1` while a panel user is signed in: `pg_request_is_panel_preview($_GET, $signed_in)` (`includes/fn/seo.php`, pure). `get_page.php` then skips `init_tracking()` and `update_visitor_page_data()`, so `visitor_content_hourly`, `visitor_stats_hourly` and the `visitors` row are untouched.
- The marker alone, without a panel session, changes nothing: a visitor who adds it is counted.
- The query string keeps the page cache out (`pg_page_cache_serve_check()` refuses any `?`), so no `page_view.php` beacon counts the preview either.

## Who adds the marker?
- `view_folders.php` `pagePreviewUrl()`, on the two page iframes: the preview pane (`renderPreview()`) and Quick look (`quickLookMediaHtml()`). "Open in new tab" keeps the plain address.
- Other panel screens that show a page in an iframe do not add it yet; a new one should.

## Why?
- #5218: every selection of a page in the File Manager raised its 30-day views and SEO impact by one; on a quiet site an operator's review drowned real visits, and the dashboard, the API's most-read list, llms.txt and the impact order read the same counts.
- Not covered: record view counters (`pg_sfv_count_view()`) and the performance measurement, which follow their own rules.

## Tests?
- `tests/panel_preview_not_a_visit_test.php`.
