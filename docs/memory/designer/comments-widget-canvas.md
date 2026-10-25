---
name: comments-widget-canvas
description: The comments widget on the canvas and in the Preview: visitor states, the thread flag twin, hide versus fade, sample comments, the Preview's page, ?r=, unsaved settings, anonymous background renders
---

## What does the canvas draw?
- One visitor state at a time (`_sdCommentsCanvasState`, editor only): comments, replying, no comments, member, sign in, closed, staff, e-mail ([[comments-widget-email]]; `_sdCommentsCanvasStates()`), picked by chips on the band (`_sdStateChips()`, `sd-chips-wrap`; also the login region's `_sdSessionChips()`) or the panel's "Canvas shows" (`_sdCommentsCanvasSect()`).
- `_sdCommentsCanvasFacts(state, cfg)` -> `_sdCommentsThreadFlags()`, a line-for-line twin of `pg_comments_widget_thread_flags()` (23 flags, compared under Node): a new PHP flag goes in both (check_bindings misses it).

## Hide or fade?
- Hide (`sd-state-off`, `_sdCommentsFlagOff()` in buildNodeEl; an id's weight beats `d-flex` wrappers): a node whose thread flag the state has false; the loop area without comments. A row flag (`comment_*`) hides only in a sample; the card shows every part.
- Fade (`sd-binding-off`, `_sdCommentsBindingOff()`): what the page's settings or the site switch off; the facts keep `rating_on`, `files_on`, `watching_on` on in every state, so it fades, never hides.
- Table structure nodes (buildTableNodeEl) take no state class.

## What does picking a node do?
- `_sdCommentsStateFor(root, node, cfg)` reads the thread flags from root to node (a loop area needs comments): the state drawn if it shows them, else the first that does. `_sdSelectionNeedsCanvas()` and buildSharedRefEl use it, so a node picked in the layer tree is drawn. A chip hiding the selection moves it to the widget (`_sdSetCommentsCanvasState()`).

## What are the sample comments?
- `_sdAppendCommentSamples()`, after the card during the widget's render: three `_sdCommentSamples()` (a reply with a quote, flat = root, threaded = reply; one awaiting approval, own unless staff; one with stars and a file) by Michael Brooks, Emily Turner, Daniel Ross (card: Jane Cooper; [[designer-block-sample-content]]). `buildNodeEl()` of the loop area's children while `_sdCommentSample` holds flags and values (read first by `_displayValueForBinding()`), then ghost DOM (no toolbar, `data-sd-id`, id; inert). Off with the sample switch (`_sdGhostsOn`), without comments, in an e-mail.
- Token previews: `_SD_SHORT_LABEL_TOKENS`; intro, closed message and label read the page's settings (`_sdCommentsPageToken()`). A comments token in a rich-text node's slot (drawn as HTML) is escaped as on the page (`_sdCommentsTextToken()`, not HTML tokens): an operator's `<img onerror>` intro stays text.

## How does the Preview draw it?
- `_sdPreviewWidgetPayload()` adds `comment_settings` (`_sdCommentSettingsValues()`) with a comments widget on the page: a changed setting asks again.
- `preview_widgets` (`pg_designer_preview_input()`): a 'hidden' page (`pg_designer_page_access()`) -> 0; the editor's settings only for 'edit', else stored ones (they could open a thread the user may not change). `pg_designer_preview_widgets()` sets `pg_sw_render_context(page_id)`, an empty claim record, `pg_comments_preview_page()` (via `pg_comment_editor_columns()`), for a comments widget `?r=` = the newest record of the page's stored form_item_view (`_pg_preview_newest_record_code()`); restored in finally. The renderer applies `pg_comments_page_override()` (comment columns, as strings).
- An unsaved page (0) draws nothing, an unsaved record widget no `?r=`; links carry the api.php address (the link guard stops them).
- `pg_comments_widget_audience()`: a background render (`pg_seo_rendering()`: Preview, SEO pass) and the search index draw anonymously, never touching the session (no liveform, CAPTCHA, token; published only). A Messages node there still prints the editor's add_comment state, as every widget's.

## How was it checked?
- `tests/comments_widget_designer_test.php`: twin, states, samples, slot escape, chips, override, audience, Preview input.
