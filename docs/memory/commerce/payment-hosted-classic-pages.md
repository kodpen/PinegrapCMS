---
name: payment-hosted-classic-pages
description: The hosted card driver's box (Stripe Payment Element) on the classic order preview and express order pages, built-in and custom layouts; how payment_hosted.js finds a card section with no mark
---

## Where is the box printed?
- Wherever `_eo_hosted_card_html($amount_cents)` answers (a hosted card driver, `pg_payment_hosted_card_driver()`, and an amount above 0). Its signature stays: the designer cart widget (`widgets_cart.php`) calls it too.
- Built-in layouts of `get_order_preview.php` and `get_express_order.php`: inside `#credit_debit_card_fields`, before a `<div data-pg-eo-cc-fields>` around the card table; the wrapper is printed only with a box, so other gateways get the old markup. The surcharge message stays outside the wrapper: `takeCardSection()` hides every child of the section.
- Custom layouts of both pages: appended to `$system`; the designer's Order Preview widget prints the same `$d['system']`.
- Amount: built-in `$card_charge_total` (`$grand_total`, or `$grand_total_with_surcharge` when the surcharge is added); custom `$total_with_surcharge` when above 0, else `$total`. The pages' calculations are unchanged (owner decision); `submit_order.php` charges its own total.

## How does payment_hosted.js find the card section?
- `[data-pg-eo-cc-fields]` first. Else (custom layouts saved before the mark; they cannot be edited) the smallest element holding every card input (`card_number`, `expiration`, `expiration_month`, `expiration_year`, `card_verification_number`, `cardholder`; inputs and selects): the `.row` inside `.credit_debit_card` in the shipped templates, so the surcharge note beside it stays shown. A table part climbs to the table's container. Refused (null) when it reaches the form or holds any other named field.
- No section: the box stays where `$system` printed it and follows the payment method; the card inputs are still switched off.
- The card inputs stay in the DOM, disabled and not required: `frontend.src.js` `init_payment_method()` reads `card_number` and its siblings and would throw without them. Disabled inputs are not posted and skip form validation, so its `required` toggling does no harm.

## Why?
- Decided in #2407 (option B): the active card driver's fields are drawn on the classic pages too, a step after the Stripe webhook. Before, a store on `Stripe PaymentIntents` with a classic page got raw card inputs that the driver refuses.

## How is it tested?
- `tests/payment_hosted_classic_test.php`: the four places read from the source; `payment_hosted.min.js` run under Node on a small DOM stand-in and a fake Stripe.js (built-in, template row, table, card inputs among billing fields).

Related: docs/memory/commerce/payment-driver-stripe.md, docs/memory/commerce/card-surcharge-rounding.md, docs/memory/plans/payment-driver-layer.md
