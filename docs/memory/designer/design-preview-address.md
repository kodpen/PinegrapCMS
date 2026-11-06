---
name: design-preview-address
description: design_preview.php, the panel's preview address: a template page, its tour, or a visual design page as a first-time visitor sees it; who may open it, what is left out, caching
module: design_previews
---

## What does design_preview.php draw?
- `?template=&page=&look=&palette=`: one page of a template from its file (`pg_design_template_preview_html()`, custom templates too); no page: the first of `pg_design_template_preview_pages()` (the home page). Its links lead to the template's other pages at this address.
- `&tour=1`: `pg_design_tour_html()` with "Use This Template" leading to `add_system_style.php?start=template&template=&look=&palette=` (`pg_design_preview_use_href()`).
- `?page_id=`: a visual design page (`pg_page_visual_design_sql()`, not in the recycle bin; drafts too) through `pg_seo_render_page()` → `pg_background_render()`.
- Look and palette are taken literally: absent means none (plain Bootstrap), not the template's own. A caller that wants the template's theme passes `pg_design_template_theme()`'s keys.
- The query is read by `pg_design_preview_request()` (pure): a page id wins over a template; unknown looks, palettes and bad keys are dropped. Nothing to draw: a plain page (`pg_design_preview_placeholder_html()`) with 404.

## Who may open it?
- GET or HEAD only (else 405). `PG_ANONYMOUS_RENDER` before `init.php`, as `audit_run.php`: the request signs nobody in; the operator is read from `$_SESSION['sessionuserid']` (an enabled user) and admitted when `pg_designer_access()` is not none, else 403.
- A design page the operator may not know of (`pg_designer_page_access()` 'hidden') reads as missing (404). Under an anonymous run that function asks `pg_folder_edit_access()` with the row's own id and role, since `check_edit_access()` reads `USER_LOGGED_IN`, which is false there.
- The session is closed before anything is drawn: the editor home loads many pictures at once, and an open session would make them wait for each other.

## How is a design page drawn as a first-time visitor?
- `$_SESSION` and `$_COOKIE` emptied for the render. The background render leaves out the consent banner and the statistics tag and counts no returning visit (`pg_page_fact_background()`); the staff bar, the chat bubble, the visit record and the page cache belong to `get_page.php`, which is not used.
- Every `<script>` is removed (`pg_design_preview_strip_scripts()`): the live picture's frame runs none anyway.
- A page type that needs a request behind it (`pg_seo_render_page()` gives NULL) or a render that throws gives the placeholder.

## What headers does it send?
- `X-Robots-Tag: noindex`, `X-Frame-Options: SAMEORIGIN`, `Cache-Control: private, max-age=300`; refusals and placeholders `no-store`.
- A design page carries an ETag from the page id, `page_timestamp`, the design's `style_timestamp` and `VERSION` (`pg_design_preview_etag()`); a matching `If-None-Match` gets 304. A shared component's change does not change it: the 5 minute cache bounds that.

Tests: `tests/design_previews_test.php`.
