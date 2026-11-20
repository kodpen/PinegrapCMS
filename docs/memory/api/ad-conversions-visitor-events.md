---
name: ad-conversions-visitor-events
description: Ad events besides the purchase (SC5): Lead for picked forms, CompleteRegistration, add to cart (20 a cart), begin checkout once per order, only with consent; the receipt's data-pg-event-id
module: ad_conversions
---

## Which events are there besides the purchase?
- `lead`, `complete_registration`, `add_to_cart`, `begin_checkout` (`pg_ad_conversion_visitor_events()` in `includes/fn/ad_conversions.php`); tests `tests/ad_conversions_visitor_test.php`. Each is ticked per service in its `config_text` row's `events`; off by default.
- Written through the same table, consent reader and queue as the purchase (`ad-conversions-queue.md`): a row per service, due (1) at once, `person` 1, sent by the general job like a purchase.

## When is a row written?
- Only for a connected service with the event ticked and the visitor's consent (`pg_ad_conversion_visitor_plan()`, which reads `pg_ad_conversion_consent_by()`). Without consent nothing is written: "Send events without the person" stays the purchase's only (C9).
- Lead: `custom_form.php` after the `form.submitted` webhook (a saved-for-later record never), for a form page listed in the service's `lead_forms`. Event id `lead-<record reference>`.
- CompleteRegistration: `pg_member_register()`, `pg_member_activate()` (`auth.php`), `pg_signin_create_member()` (`signin.php`) and a form's auto-registration in `custom_form.php`. Event id `reg-<user id>`. An account opened at checkout by `order_completion.php` sends none: a payment service's message may complete it without the visitor.
- Add to cart: `cart_action.php`'s quick add calls `pg_ad_conversion_add_to_cart()`, which returns at once when no service takes it. At most `PG_AD_CONVERSION_CART_ADDS` (20) per cart and service. Event id `cart-<order reference>-<n>`; the line (SKU, quantity, unit price in minor units) in `detail`.
- Begin checkout: `pg_ad_conversion_payment_step()` calls `pg_ad_conversion_begin_checkout()` first; event id `checkout-<order reference>`, so the unique key keeps it once per order. Its `detail` is the cart's total and lines.
- Marketplace and counter sale orders never send add to cart or begin checkout.

## Where is the person's e-mail for a Lead or a registration?
- They have no order to read it from at send time, so the e-mail goes into the row's `ids` already SHA-256 hashed (`email_sha256`, `pg_ad_conversion_visitor_ids()`); the plain address is never kept. `ids` is emptied once sent or given up, as for a purchase.
- The click id held in the session (`ad-conversions-click-ids.md`) goes only into the purchase row: SC1b's reader has one call in `ad_conversions.php` (its test counts it). These rows carry the `_fbc` cookie when the browser has it; a sender reads the row's `ids`.

## Where does the browser pixel find the purchase's event id?
- The receipt carries `<span hidden data-pg-event-id="<order reference>">` (`pg_ad_conversion_receipt_marker()`): in `get_order_receipt.php` (both layouts) and in the designer's order view widget for the order this session just completed, never in an e-mail. A pasted pixel sends that id as its `eventID` and Meta counts the purchase once (C7).

## How does Meta take them?
- Its page ticks all five (`pg_ad_conversion_meta_events()`), "Forms sent as Lead" lists the form pages, "Send events without the person" only the purchase. An event without an amount (Lead, registration) goes without `custom_data`.
