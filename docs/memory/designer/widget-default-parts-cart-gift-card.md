---
name: widget-default-parts-cart-gift-card
description: The cart's gift card part with controls (W16): the cart.item_gift_cards list, the control bindings the system owns, the required control that comes back, the old token, what is left
---

## What does the part draw?
- `pg_sw_part_cart_item_gift_card()` (editor `_sdPartCartItemGiftCard()`): `div.pg-cart-gift-cards.pg-hide-if-empty` (`data-pg-cart-gc-item="{{__item_id}}"`), a `cart.item_gift_cards` list of `fieldset.pg-cart-gc-set` (`data-pg-gc-set`), its legend "Gift card" and the number when the line holds more than one, then four `div.mb-3.pg-cart-gc-field` (`data-pg-gc-field` = the column): a label (a `span.text-danger` star when required) and the control; the delivery date's help line.
- A line that is no gift card gets no rows, so the box goes (pg-hide-if-empty, [[widget-hide-if-empty]]).

## Who owns a control?
- Owner (2026-10-27): every part is designable; the system owns what a control sends and whether it is required; a deleted required control comes back ([[widget-designability-decisions-2]]).
- A `semantic` input or textarea bound `_bindings.value` = `gift_card_email` / `gift_card_from_name` / `gift_card_message` / `gift_card_delivery_date` (`pg_sw_cart_gift_card_control_specs()`, editor `SW_CART_GIFT_CARD_CONTROLS`): `_apply_shopping_cart_bindings()` sets its id and name (`^^__gift_card_field^^<column>`, the names cart_action.php reads), value or text, type, `required` (the e-mail), maxlength and the cart form; classes, other attributes and the label's words stay the design's. A label beside it gets `for` (`pg_sw_cart_gift_card_label_for()`).
- A card without a required control gets it back at the end of its first element (`pg_sw_cart_gift_card_restore()`), on the server only.

## Where do the rows come from?
- The cart builds each card's values as before (the visitor's rejected value, then the saved row) into the item's `gift_cards`; `pg_sw_list_cart_gift_card_rows()` files them by item id. Tokens: `__gift_card_number`, `__gift_card_count`, `__gift_card_field` (the name prefix), `__gift_card_email`, `__gift_card_from_name`, `__gift_card_message`, `__gift_card_delivery_date`. Not offered to the cart link.

## How does an old layout get it?
- A node bound to `__item_gift_card_html` becomes the part's host; a row placing neither the token nor the list gets it at the end of the main loop once (`pg_sw_default_loop_tail_parts()`). The old block is still built and appended only when the row places neither (a placeholder tree).
- Ids now carry the list's row suffix too; names, values, classes, words and markers are the old block's (`tests/widget_default_parts_cart_gift_card_test.php`).

## What is left?
- The payment schedule: [[widget-default-parts-cart-schedule]]; the product order form: [[widget-default-parts-cart-form]]. A browser round (editor load, a gift card in the cart, checkout) is owed.
