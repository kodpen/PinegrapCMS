---
name: canvas-closed-blocks-widgets
description: CB0 list, the closed slots inside system widgets: section bindings and HTML values per widget kind, what each prints, template use, and what opening it takes
---

## What is closed inside a widget?
- A node bound to `section` (`_bindings.section`, options in `_sdSectionOptions()`): the server puts its own HTML inside; the canvas draws a label box ("filled by the backend"). Two keep the user's children: Recipient Picker and watcher list (`_SD_SECTION_KEEPS_CHILDREN`).
- A value of `pg_sw_raw_token_keys()` that is markup built by the renderer: the canvas draws a ‹label› or a preview (`canvas-sample-gaps.md`).
- Change today: the node's place, classes and show conditions; never its inside.

## Which sections, per widget kind? (template use)
- Comments: `comment_captcha`, `comment_rating_input`, `comment_publish` (staff), `comment_watchers`, `comment_pagination` (57).
- Custom form: `captcha` (57), `form_step`; the step progress and step buttons are Custom HTML the server writes (`custom_form_steps.php`).
- Search results: `pagination` (57).
- Login: `google_signin`, `forgot_password_link`, `register_link` (56). Registration and membership: `captcha`, `password_rules`, `google_signup`, `login_link` (57). Change password: `password_rules`.
- My Account: `order_history` (40), `subscriptions`, `bookings`, `store_credit`.
- Catalog: `group_tree`, `filters_offcanvas`, `attribute_filters` (legacy, 4). Catalog detail: `recipient_picker` (keeps children), `payment_methods`.
- Shopping cart: `applied_offers`, `quick_add`, `pending_offers`. Express order: the `eo_*` blocks (billing, payment, terms…): legacy, the starter uses `eo_field` controls.
- Checkout steps: shipping address `custom_shipping_form`, `verified_addresses`; shipping method `recipient_items`; billing `custom_billing_form`; order preview `order_recipients`, `applied_offers`, `applied_gift_cards`, `recurring_payments`, `billing_form`, `card_installments`, `installment_table`. Order form: `recurring_schedule`.
- Booking: twelve `booking_*` steps and parts (16).
- Calendar view: `calendar_picker`, `month_grid`; calendar event: `reserve_form` (no template).

## Which HTML values are blocks?
- Catalog: `__group_tree_html`, `__attribute_filters_html`, `__filters_button`, `__filters_offcanvas`, `__clear_filters_button`, `__variant_picker`, `__price_block_html`.
- Order view: `__return_actions_html` (the return forms), `__return_reason_options_html`. Cart: `__field_options_html`, `__schedule_period_options_html`.
- Ad Region: `__ad_content_html`, `__ad_caption_html`, the ad's own markup. Operator rich text (`__description`, `__comment_message_html`) is content, not a block.

## What does opening take?
- Tree: pagination (one shared family for comments and search), order history, subscriptions, bookings list, group tree, filter offcanvas, quick add, campaign gifts, applied offers, order preview parts, return actions, password rules, the sign-in and sign-up links, calendar month grid.
- Settings only (the output depends on the request or a site setting): CAPTCHA, social sign-in buttons, publish options, installments, payment methods, the step progress; a true sample on the canvas.
- Keep: the ad's own markup (made in the ad screen); option lists of a select (one `<option>` per row, drawn as one control).
