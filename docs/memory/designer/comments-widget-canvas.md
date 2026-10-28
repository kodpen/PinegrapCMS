---
name: comments-widget-canvas
description: The comments widget on the canvas: visitor states (the e-mail one too), the thread flag twin, hide versus fade, picking a node, sample comments, token previews
---

The Preview: [[comments-widget-preview]].

## What does the canvas draw?
- One visitor state at a time (`_sdCommentsCanvasState`, editor only): comments, replying, no comments, member, sign in, closed, staff, e-mail ([[comments-widget-email]]; `_sdCommentsCanvasStates(cfg)`; a recent list's: [[comments-widget-recent-editor]]), picked by chips on the band (`_sdStateChips()`, `sd-chips-wrap`; also the login region's `_sdSessionChips()`) or the panel's "Canvas shows" (`_sdCommentsCanvasSect()`).
- `_sdCommentsCanvasFacts(state, cfg)` -> `_sdCommentsThreadFlags()`, a line-for-line twin of `pg_comments_widget_thread_flags()` (25 flags, compared under Node): a new PHP flag goes in both (check_bindings misses it).
- The thread a state draws (`_sdCommentsStateThread()`): the page's (`_sdCommentsPageThread()`, by page type: [[comments-widget-item-threads]]); a product's in the states `review` (offered on every page but a catalog detail one, so a node under `is_review_thread` can be drawn and picked) and `not_bought` (catalog detail only: the closed message reads the refusal); none in the e-mail. On a product thread every state but sign in is a signed-in customer (a visitor is asked to sign in, `review_refusal`), never featured; samples are verified. `__comments_item_name` previews a sample product, group or event name, '' on a page's own thread.

## Hide or fade?
- Hide (`sd-state-off`, `_sdCommentsFlagOff()` in buildNodeEl; an id's weight beats `d-flex` wrappers): a node whose thread flag the state has false; the loop area without comments. A row flag (`comment_*`) hides only in a sample; the card shows every part.
- Fade (`sd-binding-off`, `_sdCommentsBindingOff()`): what the page's settings or the site switch off; the facts keep `rating_on`, `files_on`, `watching_on` on in every state, so it fades, never hides.
- Table structure nodes (buildTableNodeEl) take no state class.

## What does picking a node do?
- `_sdCommentsStateFor(root, node, cfg)` reads the thread flags from root to node (a loop area needs comments): the state drawn if it shows them, else the first that does. `_sdSelectionNeedsCanvas()` and buildSharedRefEl use it, so a node picked in the layer tree is drawn. A chip hiding the selection moves it to the widget (`_sdSetCommentsCanvasState()`).

## What are the sample comments?
- `_sdAppendCommentSamples()`, after the card during the widget's render: three `_sdCommentSamples()` (a reply with a quote, flat = root, threaded = reply; one awaiting approval, own unless staff; one with stars and a file) by Michael Brooks, Emily Turner, Daniel Ross (card: Jane Cooper; [[designer-block-sample-content]]). `buildNodeEl()` of the loop area's children while `_sdCommentSample` holds flags and values (read first by `_displayValueForBinding()`), then ghost DOM (no toolbar, `data-sd-id`, id; inert). Off with the sample switch (`_sdGhostsOn`), without comments, in an e-mail.
- Token previews: `_SD_SHORT_LABEL_TOKENS`; intro, closed message and label read the page's settings (`_sdCommentsPageToken()`). A comments token in a rich-text node's slot (drawn as HTML) is escaped as on the page (`_sdCommentsTextToken()`, not HTML tokens): an operator's `<img onerror>` intro stays text.

## How was it checked?
- `tests/comments_widget_designer_test.php`: twin, states, samples, slot escape, chips.
