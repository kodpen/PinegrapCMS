---
name: comments-widget-canvas
description: The comments widget on the canvas and in the Preview: visitor states, the thread flag twin, hide versus fade, sample comments, the Preview's page, ?r=, unsaved settings, anonymous background renders
---

## What does the canvas draw?
- One visitor's state at a time (`_sdCommentsCanvasState`, editor only, never saved): comments, replying, no comments, member, sign in, closed, staff (`_sdCommentsCanvasStates()`). Chips on the widget's band (`_sdStateChips()`, class `sd-chips-wrap`; `_sdSessionChips()` of the login region builds the same DOM through it) and the panel's "Canvas shows" select (`_sdCommentsCanvasSect()`).
- `_sdCommentsCanvasFacts(state, cfg)` -> `_sdCommentsThreadFlags()`, a line-for-line twin of `pg_comments_widget_thread_flags()` (22 flags; compared under Node). A new PHP flag goes into both; check_bindings does not see the twin.

## Hide or fade?
- Hide (`sd-state-off`, `_sdCommentsFlagOff()` in buildNodeEl): a node whose thread flag is false in the state; the loop area without comments. A row flag (`comment_*`) hides only inside a sample comment; the card shows every part.
- Fade (`sd-binding-off`, `_sdCommentsBindingOff()`): what the page's settings or the site switch off. So the facts keep `rating_on`, `files_on`, `watching_on` on in every state: a switched-off feature fades, it is never hidden.
- Table structure nodes (buildTableNodeEl) take no state class, as in the login region.

## What does picking a node do?
- `_sdCommentsStateFor(root, node, cfg)` reads the thread flags from the root to the node (a loop area needs comments): the state drawn when it shows them, else the first state that does. `_sdSelectionNeedsCanvas()` and buildSharedRefEl use it, so a node picked in the layer tree is drawn. A chip that hides the selection moves it to the widget (`_sdSetCommentsCanvasState()`).

## What are the sample comments?
- `_sdAppendCommentSamples()`, after the card while the widget's cfg and tree are the render's: three `_sdCommentSamples()` (a reply with a quote, flat = root, threaded = reply; one awaiting approval, own unless staff; one with stars (page setting) and a file), built with `buildNodeEl()` from the loop area's children while `_sdCommentSample` holds their flags and values (read first by `_displayValueForBinding()`). Then ghost DOM: no toolbar, `data-sd-id` or id, inert. Off with the sample switch (`_sdGhostsOn`, the real-records switch) or in a state without comments.
- Token previews: `_SD_SHORT_LABEL_TOKENS`; intro, closed message and label read the page's settings (`_sdCommentsPageToken()`).

## How does the Preview draw it?
- `_sdPreviewWidgetPayload()` adds `comment_settings` (`_sdCommentSettingsValues()`) when a comments widget is on the page, so a changed setting asks the server again.
- `preview_widgets` drops a page the user may not know (`pg_designer_page_access()` 'hidden' -> page 0). `pg_designer_preview_widgets($page_id, $widgets, $comment_settings)` sets `pg_sw_render_context(page_id)`, an empty claim record, `pg_comments_preview_page()` (the settings through `pg_comment_editor_columns()`), and for a comments widget `?r=` = the newest record of the page's stored form_item_view (`_pg_preview_newest_record_code()`); all restored in finally. The renderer applies `pg_comments_page_override()` to the page row (comment columns only, as strings).
- An unsaved page (0) draws nothing; an unsaved record widget gives no `?r=`; links carry the api.php address (the Preview's link guard stops them).
- `pg_comments_widget_audience($search, $background, ...)`: a background render (`pg_seo_rendering()`: Preview, SEO pass) and the search index draw anonymously and never touch the session: no liveform, CAPTCHA or token, published comments only. Live pages and the search index draw as before. A Messages node in a background render still prints the editor's add_comment state, as every widget's does.

## How was it checked?
- `tests/comments_widget_designer_test.php` (twin, states, samples, chips, override, audience), comments_widget tests.
