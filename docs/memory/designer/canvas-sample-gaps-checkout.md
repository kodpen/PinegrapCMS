---
name: canvas-sample-gaps-checkout
description: CS0 census, orders (CS2) part two: shopping cart, express order checkout, order preview, shipping and billing steps and their lists: canvas drawing and faults
---

## What does the shopping cart draw wrong? (`SW_SHOPPING_CART_TOKEN_GROUPS`, `cart.*`)
- Samples: the totals, the count, the five labels from the cart's settings, item name, descriptions, quantity, prices, stock warning, the form and gift card blocks, recurring schedule, tax note.
- ‹label› where the page draws a control: `__special_offer_code_form`, `__quick_add_form`, `__update_button`, `__checkout_button`, `__applied_offers`, `__special_offer_code`, `__special_offer_code_message`, `__empty_message`, `__reference_code`. "1" for `__item_qty_input` (an input on the page) and `__applied_offers_count`.
- ‹label› yes/no values: `__has_shippable_item`, `__has_recurring_items`, `__item_out_of_stock`, `__item_is_donation`, `__item_added_by_offer`, `__item_starts_recipient`, `__item_is_recurring`, `__item_starts_recurring`, `__item_recurring_editable`; values `__item_calendar_event_dates`, `__item_recurring_period`, `_payments`, `_start_date`.
- "Example": `__item_ship_to_name`, `__item_calendar_event_name`. Grey box: `__item_image`. Empty flags: `__item_has_discount`, `__item_has_low_stock`.
- Lists: `cart.item_schedule` 11 of 11 ‹label›; `cart.item_form_fields` 15 of 17 ‹label› (`__field_control`, `__field_options_html`, `__field_value`, `__field_type` …), the rest "Example"; `cart.item_form_field_options` no sample; `cart.item_gift_cards` none; `cart.items` ‹label› for `__item_id`, `__item_image_alt`.

## What does the express order (checkout) draw wrong? (`SW_EXPRESS_ORDER_TOKEN_GROUPS`, `checkout.*`)
- Samples: the cart totals, counts, item values, recipient index.
- Wrong: `__cart_subtotal_cents`, `__cart_total_cents`, `__cart_total_with_surcharge_cents` draw formatted money (the page writes whole minor units); `__recipient_address_country` and `__recipient_address_phone_number` "1"; the recipient's names, company, city, state "Example".
- ‹label›: `__currency_disclaimer`, `__form_id`, `__legal_kvkk_consent_html` (a consent box on the page), `__recipient_id`, `__recipient_address_address_1`, `_address_2`, `_zip_code`, `__item_id`, `__item_form_html` (the product form on the page). `__recipient_count` "1". Grey box: `__item_image`.
- `checkout.recipients`: the same values as above; `checkout.offers`, `.suggestions`, `.gift_cards` have samples.

## What do the order preview and the checkout steps draw wrong?
- Order preview (`SW_ORDER_PREVIEW_TOKEN_GROUPS`): only the totals, billing company and address block are samples. `__billing_name`, the two custom field labels, `__billing_form_title`, `__purchase_now_label`, `__offline_payment_label` "Example"; `__po_number` "1"; `__surcharge_percentage` money; ‹label› `__currency_disclaimer`, `__billing_email`, `__billing_phone`, `__custom_field_1`, `_2`; grey boxes `__paypal_image_url`, `__iyzico_image_url` (logos the page draws).
- Shipping address: `__recipient_name`, `__custom_form_title`, `__arrival_date_name` "Example", `__arrival_date_description` the stock blurb.
- Shipping method: `__shipping_method_description` and `__no_shipping_methods_message` draw money; `__shipping_method_cost` the money sample; ‹label› `__recipient_address_html`, `__requested_arrival_date`.
- Billing information: `__tax_exempt_label` draws money; the custom field labels, opt-in label and form title "Example".
