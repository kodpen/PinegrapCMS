---
name: widget-lists-checkout
description: The express order's lists (W24a): applied offers, offer suggestions, applied gift cards, recipients with their cart lines; family checkout, key, tokens, where rows are read, the editor starters
module: widgets_lists_checkout
---

## What do the express order's lists give?
- Owner decision (2026-10-27, W24): display lists first; the recipient address, shipping method and delivery date controls and the checkout flow stay the widget's own.
- Family `checkout` (`includes/fn/widgets_lists_checkout.php`, engine [[widget-lists]]), only in `express_order`, which also offers `cart.items` ([[widget-lists-cart]]):
  - `checkout.offers`: `__offer_code`, `__offer_text` (`pg_offer_public_label()`: the code when there is no message). Read by `pg_sw_list_checkout_applied_offers()`, which the applied offers section uses too.
  - `checkout.suggestions`: the upsell offers of `apply_offers_to_cart()`: `__offer_text`, `__offer_action_url` and `__offer_action_label` (empty without a page; the starter shows the link under a `not_empty` condition).
  - `checkout.gift_cards`: `__gift_card_code` (masked as the classic page masks it, Givex too), `__gift_card_amount`, `__gift_card_balance` (left after this order), `__gift_card_remove_url` (`remove_gift_card_from_order.php`, CSRF token). Redemption: `pg_sw_list_checkout_redeem()`, each card up to its balance in the order applied, as `submit_order()` takes them. Nothing while gift cards are off.
  - `checkout.recipients`: the express order's recipient loop names (`__recipient_name`, `__recipient_address_*`, `__recipient_index`), `__ship_to_key` (the id), `__ship_to_item_count`.

## Which key?
- `__ship_to_key`: the renderer fills it `0` after the loops; the recipient loop (`_eo_compute_recipient_tokens()`) fills it with the recipient's id. Checkout rows are under 0 only, so the editor marks a checkout list inside a recipients list as drawing nothing; a cart list there lists that recipient's lines.

## Where are the rows read?
- `_render_system_widget_express_order()` step 4c, only for the families its tree drew (`pg_sw_list_wanted()`): the cart lines from its own `$items` (`pg_sw_list_checkout_cart_items()`), the offers and gift cards with one query each, the recipients it already read. Raw values; the pass escapes them once.

## What does the editor give?
- Palette "Lists in a record": four entries; `_sdCheckoutListStarter()`: a success alert list, info alerts, a gift card list group with Remove, recipient cards holding the cart starter. A list in the item loop is marked as drawing nothing, as in the cart.

## Are the offer texts translated?
- Yes (W24b): owner type `offer` in the catalog group ("Products, product groups and offers"), fields `pg_tr_offer_fields()` of every enabled offer; the pass over the finished page swaps them where they print ([[content-translation-uses]]). The code is never translated.

## What is left?
- A browser round is owed.
