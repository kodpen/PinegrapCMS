---
name: widget-starters-detail
description: W20b-2 whole-layout starters of the detail widgets (#3234): the starterKeep markers that put the Default tree's parts in place, what each kind keeps, one main loop
module: widgets_starters
---

## How does a whole layout keep the default's parts?
- A node whose only prop is `starterKeep` stands for the first node of the kind's Default tree matching it (`_sdLgKeepMatch()`): `loop` (the main loop area, not a list with `source`), `content:<type>`, `region:<type>`, `<binding>:<value>` (`section:quick_add`, `eo_visible_if:has_payment`, `action:coupon_form`, `text:__currency_selector`, `show_if:__has_breadcrumb`).
- Kept so: the variant pickers, recipient picker, back-in-stock form, carousel, comments region, cross-sell loop (product); applied offers, campaign gifts, quick add, offer code form, recurring summary, currency picker (cart); currency picker, empty cart, recipients, applied offers, gift cards, recurring, payment block, terms (order preview); the record's loop with the form's fields (form detail). The starter writes the rest itself with the same bindings.
- A body has exactly one main loop area: the kept one, or its own (cart rows; the order preview's empty one).
- Why markers: the payment block, variant pickers and the cart's own forms are long trees the Default builds with editor helpers; a copy in PHP would drift from them. Copied, so the operator edits them like any part once Keep is pressed.
