---
name: canvas-sample-gaps-orders
description: CS0 census, orders (CS2): order view and order receipt e-mail card, its lists (items, product forms, gift cards, shipments, returns): canvas drawing and faults
---

## What does the order view and its receipt e-mail draw wrong? (`SW_ORDER_VIEW_TOKEN_GROUPS`)
- Samples today: number, date, status, notes, billing name parts, city, country, payment, tracking, the six totals, item name, price, quantity, total.
- Wrong: the shipping address (`__shipping_name`, `_first_name`, `_last_name`, `_company`, `_phone`, `_address_1`, `_address_2`, `_city`, `_state`, `_zip`, `_country`, `_full_address`, `__shipping_address`) draws as money ("shipping" in the name). `__order_item_count`, `__order_unit_count`, `__installment_count`, `__item_number` draw "1"; `__order_status_text`, `__order_status_stage`, `__billing_name` draw "Example".
- ‹label›: `__order_id`, `__member_id`, `__special_offer_code`, `__reference_code`, `__billing_salutation`, `__billing_email`, `__billing_phone`, `__billing_address_1`, `__billing_address_2`, `__billing_zip`, `__billing_full_address`, `__withdrawal_text`, `__tracking_codes`, `__item_id`, `__item_image_alt`, `__item_ship_to`, `__item_has_form_data`, `__item_has_gift_card`.
- Grey box: `__item_image`. Empty flag: `__has_shipping`.
- Entity values (`SW_ORDER_ENTITY_TOKENS`, `SW_ORDER_LINE_ENTITY_TOKENS`): ‹label› for the custom fields 1-2, fax, currency, paid, cancelled and refunded dates, cancellation reason, line VAT, payment period, first payment, event date, added by offer; "1" for the PO number, shipped quantity, payment count; money for `__order_is_tax_exempt`; "Example" for the refund status and event name.

## What do the order's lists draw? (`order.*` in `SW_LIST_SOURCES`)
- `order.items`: as the main loop above; `__item_has_form_data` and `__item_has_gift_card` are ‹label›, so a "not empty" condition on them always shows.
- `order.item_forms` / `order.item_form_fields`: `__form_name` and `__field_label` "Example", `__form_number` "1", `__form_key`, `__form_has_legend`, `__field_value_html`, `__field_info_html`, `__field_is_info` ‹label›. Because `__field_is_info` is not empty, every field takes the "Information" branch of `_sdPartOrderItemForm()`: the owner's picture (three ‹Information (HTML)›, no answer).
- `order.item_gift_cards`: `__gift_card_email`, `_message_html`, `_delivery` ‹label›; `__gift_card_number`, `_count` "1"; `__gift_card_from_name` "Example".
- `order.shipments`: every address value is money (as above); 4 of 16 have samples.
- `order.returns` (20 values: 14 ‹label›, the rest "Example" or "1"), `order.return_lines`, `order.return_deliveries`, `order.return_delivery_lines`: no samples at all; `__return_reason_options_html` and `__return_actions_html` draw a ‹label› where the page draws controls.
- `order.parcels`, `order.steps` and `account.orders` (but `__order_id`) have samples; `SD_LIST_SAMPLE_ROWS` gives `order.steps` its three rows.

## Which part belongs to the cart and checkout?
- `canvas-sample-gaps-checkout.md`.
