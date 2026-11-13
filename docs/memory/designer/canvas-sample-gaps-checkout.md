---
name: canvas-sample-gaps-checkout
description: CS0 census, orders part two: what the cart, express order checkout, order preview and the shipping and billing steps still draw without a sample after CS2 (#9214)
---

## What did CS2 settle?
- The cart, the checkout, the order preview and the shipping address, shipping method and billing information steps draw the sample order (`canvas-sample-order.md`): totals that add up, both lines, the two addresses, the recipient, the method and its cost, labels and custom fields; the `…_cents` values are whole minor units.
- The control slots (offer code form, Update and Checkout, applied offers, quick add, quantity stepper, saved cart link, consent sentence, field options) draw the page's markup instead of a ‹label›.

## What is left?
- `__item_image`, `__paypal_image_url`, `__iyzico_image_url` draw the sample photo (`canvas-loop-as-published.md`, #9233).
- The cart's product form controls (`form_field` value bindings) draw empty inputs, and the cart line's own HTML previews (recipient heading, recurring heading, calendar event, out-of-stock note) show on every line whatever the line's flags say.
