---
name: widget-default-parts-order-items
description: The order view's item form and gift card parts (W16, #1863): the three item lists, their keys and tokens, the loop tail, the boxes' flags, raw tokens, what the cart keeps
---

## What do the parts draw?
- Item form (`pg_sw_part_order_item_form()`): `div.pg-ov-item-form.mt-2` with `data-pg-ov-item-form="{{__item_id}}"`, shown by `__item_has_form_data`; an `order.item_forms` list of `fieldset.pg-ov-item-form-block` with a legend (shown by `__form_has_legend`: the form has a name or more than one block) of `__form_name` and ` (^^__form_number^^ / ^^__form_count^^)` when `__form_count gt 1`; inside, an `order.item_form_fields` list: `div.mb-2` label above `div.pg-ov-form-value` (`__field_value_html`) when `__field_is_info` is empty, else `div.mb-2.small` with label and `__field_info_html`.
- Gift card (`pg_sw_part_order_item_gift_card()`): `div.pg-ov-item-gift-card` (`data-pg-ov-item-gift-card`), shown by `__item_has_gift_card`; an `order.item_gift_cards` list of fieldsets: legend "Gift card" (translated when the part is made) and the number, then Amount (`strong`), Recipient Email, Your Name, Message, Delivery Date, each label above its value.
- The number sits in one text with its tokens because the renderer indents children: separate spans would read "( 1 / 3 )".

## Which lists and keys?
- `order.item_forms` and `order.item_gift_cards` read `__item_id`: the item loop's row, or an `order.items` row (its rows carry `__item_id` and both flags). `order.item_form_fields` reads `__form_key` (item id × 1000 + block number, so a key stays digits).
- Rows: `pg_sw_list_order_item_parts_rows()` (pure; marks `has_form_data`, `has_gift_card` on the items), fed by `pg_sw_list_order_item_parts_read()`: one query each for form_fields, form_data, order_item_gift_cards, only for lists the tree drew. Values as the old blocks: several values of a field joined with ", ", rich text filtered, others `prepare_form_data_for_output()`; an empty information field gives no row; a card not filled in gives none; no date reads "Immediate"; the amount is the unit price formatted from minor units.
- `__field_value_html`, `__field_info_html`, `__gift_card_message_html` are raw (`pg_sw_raw_token_keys()`, editor `SW_ATTR_MARKUP`): no attribute or condition takes them.

## How does an old layout get them?
- A node bound to `__item_form_data_html` or `__item_gift_card_html` (or the literal token alone) becomes the part's host. A loop row placing neither (token or list) gets the part at the end of the main loop once (`pg_sw_default_loop_tail_parts()`, `pg_sw_main_loop_ref()`, editor `SW_DEFAULT_LOOP_TAILS`, `_sdDefaultLoopTails()`); deleted, it comes back, as the old block did. The starter builds both parts.
- The two readonly builders and the order view's fallback append are gone; the order view no longer fills the two old tokens.

## Editor rules?
- `_sdListPlaceOk()` lets an item's list into the item loop; `_sdOrderItemListKey()` names the list giving its key; outside an item the band is amber ("outside an item of the order"). No palette item: the parts carry the lists.

## What is left?
- The cart's editable twins: [[widget-default-parts-cart-form]], [[widget-default-parts-cart-gift-card]].
- Tests: `tests/widget_default_parts_item_test.php` (old vs new by visible text, classes, markers; e-mail; the editor twin).
