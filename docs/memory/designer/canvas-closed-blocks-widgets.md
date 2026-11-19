---
name: canvas-closed-blocks-widgets
description: Sections and HTML values inside system widgets on the canvas today, per widget kind: opened as a tree, drawn true (settings only) or kept, with the step and memory file of each
---

## How is a slot inside a widget closed or open?
- A node bound to `section` (`_bindings.section`, `_sdSectionOptions()`): the server puts its own HTML inside. Unopened, the canvas draws a true sample or, for a slot no step took up, a label box. A section whose children print as designed is in `_SD_SECTION_KEEPS_CHILDREN` (recipient picker, watcher list, password rules, step buttons).
- A `pg_sw_raw_token_keys()` value that is markup: the canvas draws its preview (`_SD_HTML_PREVIEW_TOKENS`, `canvas-sample-overview.md`).
- Tree: "Convert to design elements" on the section's panel; a saved page keeps its section (rules: [[canvas-closed-blocks]]).

## Comments, search, custom form?
- Comments (CB2, #9716, [[comments-widget-open-slots]]): pagination a tree, shared with Search results; CAPTCHA, star rating input, publish options drawn true; watchers keep their children. The CAPTCHA sample is the sum question even where a provider replaces it.
- Custom form (CB4, #9806, [[custom-form-open-slots]]): step buttons a tree (`form_step_nav`), the steps' progress and CAPTCHA drawn true.

## Sign-in and sign-up?
- CB7 (#9895, [[member-widgets-open-slots]]): password rules and the sign-in, sign-up and forgotten password links as elements; the social sign-in and sign-up buttons drawn true (settings only).

## Shop?
- Catalog detail (CB6a, #9849, #9871): payment methods drawn true; price block and variant picker drawn true ([[catalog-detail-variant-price-blocks]]); recipient picker keeps children.
- Catalog (CB6b, #9890, [[catalog-listing-open-blocks]]): `group_tree`, `filters_offcanvas`, legacy `attribute_filters` drawn true.
- Cart (CB6c, #9901, [[cart-open-blocks]]): `applied_offers`, `quick_add`, `pending_offers` drawn true.
- Order preview (CB6d, #9920, #9966): offers, gift cards, recurring payments and recipients trees; installments drawn true ([[checkout-open-blocks]], [[checkout-open-recipients]]).
- My Account (CB6e, #10002, #10163): order history, bookings, subscriptions, store credit trees ([[account-order-open-blocks]], [[account-subscription-credit-open-blocks]]). Order view: return actions are the return moves part (W16).

## Booking and calendar?
- Booking (CB8, #9918, [[booking-widget-open-parts]]): the five steps and the details form are element trees; the six parts `pg_booking.js` fills drawn true.
- Calendar (CB9, #9932; CB9b, #9981, [[calendar-widgets-open-slots]]): `month_grid` converts to the grid lists; `calendar_picker` and the event's `reserve_form` drawn true.

## What stays closed, and why?
- A form's own answers: `billing_form`, `custom_shipping_form`, `custom_billing_form`, `__ship_to_form_html`, `__line_forms_html`, drawn as their form draws them.
- A section's forms with their token: `__subscription_actions_html`, `store_credit_convert` (the points form).
- A select's option list (`__return_reason_options_html`, `__field_options_html`, `__schedule_period_options_html`): one control.
- The ad's own markup (`__ad_content_html`, `__ad_caption_html`, made in the ad screen); operator rich text is content.
- Drawn per visitor by other code: the booking calendar's date control, the deposit payment box.
- No step took up: `verified_addresses`, `recipient_items`, `recurring_schedule` (own memory files), the express order's legacy `eo_*` blocks (the starter uses `eo_field` controls).
