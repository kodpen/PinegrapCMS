---
name: widget-default-parts
description: W16 placed beats pushed: the one placement rule (pg_sw_placed()), default parts that draw old HTML tokens with real design elements, the order timeline and status badge, slices left
---

## What is the rule?
- A part the designer placed always wins; a renderer adds its own copy of a block only when the saved tree places none of its tokens: `pg_sw_placed($tree, $tokens)` (`includes/fn/widgets_parts.php`). A binding, a literal `^^token^^` or an attribute `{{token}}` places; a condition never does (`pg_sw_cond_tree_json()`); the name is matched whole.
- Uses so far: the order view's legal documents card and withdrawal line, returns block, item form and gift card rows (these read the item template's nodes, no longer the rendered row).

## What is a default part?
- An old token that stood for server-built markup is drawn by real design elements the designer can select and change. `pg_sw_default_part_tokens()`: kind => (token, builder) pairs (pairs, so `check_bindings.php` does not read them as filled).
- Server: `pg_sw_default_parts_apply()` in `_expand_system_widgets()`, on each widget's tree (Preview overrides too) before its renderer. Editor: `_sdDefaultParts()` when the widget loads (beside `_sdDropMessagesNodes()`), nothing marked unsaved; the next save stores the part. The starter builds the parts directly.
- The bound node keeps its classes, attributes, name and other bindings; its text binding goes; a content node becomes the element it drew (`pg_sw_part_host()`); any other node moves into a `<div>`. A text that is the token alone counts as bound.
- Twins: `SW_DEFAULT_PARTS`, `_sdPart*` = `pg_sw_part_*`; `tests/widget_default_parts_test.php` runs both under Node.

## How does the owner's "not byte-identical" read? (2026-10-26, option B)
- Same words in the site's language, same classes in the same order, forms keep names, ids and markers; hidden details may change. The test compares the old block with the new output by visible text and class order, and checks the e-mail output.

## What does the order view draw now?
- Timeline: `ul.pg-ov-timeline` > `order.steps` list > `li.pg-ov-timeline-item`: an icon per `__step_kind` (`eq`), shipped and delivered with an `info`/`success` or, when `__step_is_estimate`, a `secondary` icon; label `__step_label`, ` — ` and `__step_note` when there is a note, date `__step_date`. Its look moved from the inline `<style>` to `pg_carousel_layout.css` (v4), loaded when the output holds `pg-ov-timeline`. `_eo_render_order_timeline()` and the `__timeline` value are gone; `has_timeline` stays.
- Status badge: six spans, `show_if __order_status_stage eq <stage>` (`pg_sw_order_status_stages()`), text `__order_status_text` ("Awaiting Payment" for an unpaid transfer), classes from `status_class_<stage>` or `_pg_order_status_badge_class()`'s defaults, baked in when the part is made. The panel's "Status Badge Classes" section is gone: the classes are edited on each badge. `__order_status_badge` has no value.
- E-mail: `pg_sw_parts_mail_styles()` writes `pg_sw_parts_mail_style_map()` inline (no flex, positioning or pseudo element; icon colour from its `bg-*`), before the element's own style.

## What is left after #1808 (slice 1)?
- Form-carrying blocks: `__item_form_data_html`, `__item_gift_card_html` (order view, cart rows), returns `__return_form`/`__returns`, the legal card, `__cancel_form`.
- Express order offers; cart row form data; catalog listing body; variant picker auto-inject; cross-sell; breadcrumb; filters (`__filter_chips` …); comment stars. Each check moves to `pg_sw_placed()`; a token freed from a rendered-template `strpos()` leaves `pg_sw_cond_denied_tokens()`. Then the raw keys in `pg_sw_raw_token_keys()` shrink.
- Canvas: all six badges and every timeline icon show at once until W19's state preview.
- A literal token inside a longer text is not given a part (prints empty); a condition on `__timeline` or `__order_status_badge` reads empty.
- Owed: a browser round (editor load, Preview, a receipt e-mail).
