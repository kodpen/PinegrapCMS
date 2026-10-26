---
name: widget-default-parts
description: W16 placed beats pushed: the one placement rule (pg_sw_placed()), default parts that draw old HTML tokens with real design elements, tail and lead parts, slices left
---

## What is the rule?
- A part the designer placed always wins; a renderer adds its own copy of a block only when the saved tree places none of its tokens: `pg_sw_placed($tree, $tokens)` (`includes/fn/widgets_parts.php`). A binding, a literal `^^token^^` or an attribute `{{token}}` places; a condition never does (`pg_sw_cond_tree_json()`); the name is matched whole.
- Uses: the cart's item form and gift card rows (the item template's nodes). The order view's parts and cards: [[widget-default-parts-order]], its item blocks [[widget-default-parts-order-items]]. Comment stars: [[widget-default-parts-comments]]. Catalog breadcrumb: [[widget-default-parts-breadcrumb]].

## What is a default part?
- An old token that stood for server-built markup is drawn by real design elements the designer can select and change. `pg_sw_default_part_tokens()`: kind => (token, builder) pairs (pairs, so `check_bindings.php` does not read them as filled).
- Server: `pg_sw_default_parts_apply()` in `_expand_system_widgets()`, on each widget's tree (Preview overrides too) before its renderer. Editor: `_sdDefaultParts()` when the widget loads (beside `_sdDropMessagesNodes()`), nothing marked unsaved; the next save stores the part. The starter builds the parts directly.
- The bound node keeps its classes, attributes, name and other bindings; its text binding goes; a content node becomes the element it drew (`pg_sw_part_host()`); any other node moves into a `<div>`. A text that is the token alone counts as bound.
- A tail part: a block the renderer appended after the widget is added to the end of the tree once when the tree places none of its tokens (`pg_sw_default_tail_parts()`, editor `_sdDefaultTails()`, the starter too); from then on it is placed. Deleting it brings it back, as the old block came back. Never on a template's placeholder (`pg_sw_tree_is_placeholder()`): the editor fills that with the starter.
- A lead part: a block put before the main loop, added just before it once (`pg_sw_default_lead_parts()`, `_sdDefaultLeads()`).
- A section binding or content type stands for a token: `section:<name>`, `content:<type>` (`pg_sw_part_token_of()`).
- Twins: `SW_DEFAULT_PARTS`, `SW_DEFAULT_TAILS`, `_sdPart*` = `pg_sw_part_*`; `tests/widget_default_parts_test.php` runs both under Node.

## How does the owner's "not byte-identical" read? (2026-10-26, option B)
- Same words in the site's language, same classes in the same order, forms keep names, ids and markers; hidden details may change. The test compares the old block with the new output by visible text and class order, and checks the e-mail output.

## What is left after #1808, #1824, #1863, #1908 and #1945?
- Form blocks: the cart rows' editable `__item_form_data_html`, `__item_gift_card_html` (placed by `pg_sw_placed()` since #1863; parts with controls are an open owner question), `__returns`/`__return_form` (still server-built inside the returns card), `__cancel_form`. The order view's item blocks: [[widget-default-parts-order-items]].
- Express order offers; cart row form data; catalog listing body; variant picker auto-inject; cross-sell (`pg_civ_variants.js` redraws the old cards on a variant switch: a part needs that redraw first, or loses the design's changes); filters (`__filter_chips` …). Each check moves to `pg_sw_placed()`; a token freed from a rendered-template `strpos()` leaves `pg_sw_cond_denied_tokens()`. Then the raw keys in `pg_sw_raw_token_keys()` shrink.
- Canvas: the band's state chips draw one status badge at a time ([[widget-condition-canvas]]); the timeline's card draws `created`, its sample rows paid, shipped and delivered.
- A literal token inside a longer text is not given a part (prints empty); a condition on `__timeline` or `__order_status_badge` reads empty.
- Owed: a browser round (editor load, Preview, a receipt e-mail).
