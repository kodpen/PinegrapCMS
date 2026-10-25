---
name: comments-widget-preview
description: The comments widget in the designer's Preview: the editor's unsaved comment settings, the page id and ?r= it draws with, an unsaved page, lists of recent comments, anonymous background renders
---

The canvas (states, fades, samples): [[comments-widget-canvas]].

## How does the Preview draw it?
- `_sdPreviewWidgetPayload()` adds `comment_settings` (`_sdCommentSettingsValues()`) with a comments widget on the page: a changed setting asks again.
- `preview_widgets` (`pg_designer_preview_input()`): a 'hidden' page (`pg_designer_page_access()`) -> 0; the editor's settings only for 'edit', else stored ones (they could open a thread the user may not change). `pg_designer_preview_widgets()` sets `pg_sw_render_context(page_id)`, an empty claim record, `pg_comments_preview_page()` (via `pg_comment_editor_columns()`), for a comments widget `?r=` = the newest record of the page's stored form_item_view (`_pg_preview_newest_record_code()`); restored in finally. The renderer applies `pg_comments_page_override()` (comment columns, as strings).
- An unsaved page (0) draws nothing, an unsaved record widget no `?r=`; links carry the api.php address (the link guard stops them).
- `pg_comments_widget_audience()`: a background render (`pg_seo_rendering()`: Preview, SEO pass) and the search index draw anonymously, never touching the session (no liveform, CAPTCHA, token; published only). A Messages node there still prints the editor's add_comment state, as every widget's.
- A list of recent comments ([[comments-widget-recent-editor]]): no `comment_settings` for a page placing only lists, no `?r=`; it draws what a visitor who is not signed in sees.

## How was it checked?
- `tests/comments_widget_designer_test.php`: override, audience, Preview input.
