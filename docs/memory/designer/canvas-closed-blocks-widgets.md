---
name: canvas-closed-blocks-widgets
description: CB0 list, the closed slots inside system widgets: section bindings and HTML values per widget kind, what each prints, template use, and what opening it takes
---

## What is closed inside a widget?
- A node bound to `section` (`_bindings.section`, options in `_sdSectionOptions()`): the server puts its own HTML inside; the canvas draws a label box ("filled by the backend"). Two keep the user's children: Recipient Picker and watcher list (`_SD_SECTION_KEEPS_CHILDREN`).
- A value of `pg_sw_raw_token_keys()` that is markup built by the renderer: the canvas draws its preview (`_SD_HTML_PREVIEW_TOKENS`, `canvas-sample-overview.md`).
- Change today: the node's place, classes and show conditions; never its inside.

## Which sections, per widget kind? (template use)
- Comments: `comment_captcha`, `comment_rating_input`, `comment_publish` (staff), `comment_watchers`, `comment_pagination` (57).
- Custom form: `captcha` (57), `form_step`; the step progress and step buttons are Custom HTML the server writes (`custom_form_steps.php`).
- Search results: `pagination` (57).
- Login: `google_signin`, `forgot_password_link`, `register_link` (56). Registration and membership: `captcha`, `password_rules`, `google_signup`, `login_link` (57). Change password: `password_rules`.
- My Account: `order_history` (40), `subscriptions`, `bookings`, `store_credit`.
- Catalog: `group_tree`, `filters_offcanvas`, `attribute_filters` (legacy, 4): drawn true since CB6b ([[catalog-listing-open-blocks]]). Catalog detail: `recipient_picker` (keeps children), `payment_methods` (drawn true since CB6a1, #9849).
- Shopping cart: `applied_offers`, `quick_add`, `pending_offers` (campaign gifts): drawn true since CB6c ([[cart-open-blocks]]). Express order: the `eo_*` blocks (billing, payment, terms…): legacy, the starter uses `eo_field` controls.
- Checkout steps: shipping address `custom_shipping_form`, `verified_addresses`; shipping method `recipient_items`; billing `custom_billing_form`; order preview `order_recipients`, `billing_form`. The order preview's `applied_offers`, `applied_gift_cards`, `recurring_payments` open into lists and `card_installments`, `installment_table` are drawn true since CB6d1 ([[checkout-open-blocks]]). Order form: `recurring_schedule`.
- Booking: twelve `booking_*` steps and parts (16).
- Calendar view: `calendar_picker`, `month_grid`; calendar event: `reserve_form` (no template).

## Which HTML values are blocks?
- Catalog: `__group_tree_html`, `__attribute_filters_html`, `__filters_button`, `__filters_offcanvas`, `__clear_filters_button`, `__variant_picker`, `__price_block_html`.
- Order view: `__return_actions_html` (the return forms), `__return_reason_options_html`. Cart: `__field_options_html`, `__schedule_period_options_html`.
- Ad Region: `__ad_content_html`, `__ad_caption_html`, the ad's own markup. Operator rich text (`__description`, `__comment_message_html`) is content, not a block.

## What does opening take?
- Tree: pagination (one shared family for comments and search), order history, subscriptions, bookings list, the order preview's recipients (CB6d2), return actions, password rules, the sign-in and sign-up links, calendar month grid.
- Settings only (the output depends on the request or a site setting): CAPTCHA, social sign-in buttons, publish options, installments (CB6d1), payment methods, the category tree and filters (CB6b), the cart's applied offers, campaign gifts and quick add (CB6c), the step progress; a true sample on the canvas.
- Keep: the ad's own markup (made in the ad screen); option lists of a select (one `<option>` per row, drawn as one control); a custom shipping or billing form's answers in the checkout (`custom_shipping_form`, `custom_billing_form`, the order preview's `billing_form`): drawn as their form draws them.
