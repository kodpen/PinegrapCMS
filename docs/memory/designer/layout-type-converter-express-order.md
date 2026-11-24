---
name: layout-type-converter-express-order
description: An express order page's layout file into the Express Order widget (LE9b, #11486): the cart's map, the theme's billing markup, the widget's sections, controls
module: layout_convert
---

## What does the express order's map do?
- `_pg_lc_express_order_map()` (`includes/fn/layout_convert.php`) via `pg_layout_piece_map('express order')` ([[layout-type-converter]]). It starts from the cart's map ([[layout-type-converter-cart]]): the same row values, row flags, two tables as one list with `lists`, gift card, form and schedule fields printed whole.
- The widget's form posts to `express_order.php`, the classic handler, so the theme's own controls keep their classic names: billing fields, `opt_in`, `po_number`, `custom_field_1/2`, `referral_source`, `update_contact`, `tax_exempt`, `special_offer_code`, `gift_card_code` with `submit_apply_gift_card`.

## Which blocks does the widget print whole?
- Sections: `$messages` → `eo_errors_notices`; each recipient's shipping (`$recipient['shipping']`) → `eo_shipping` in a `<tr><td colspan="6">` under `has_shipping`; `$credit_debit_card` → `eo_payment_methods` (PayPal and offline blocks are in it); `$terms_url` → `eo_terms`; `$applied_offers` → `eo_applied_offers`; the payment periods loop → `eo_payment_periods` (`pg_cart_payment_periods_html()`).
- Skipped, the widget's own: the special offers block (upsell offers above the form), the staff offline payment box, the empty cart (the widget's empty state).
- Dropped and reported (state): quick add on (`quick_add`), the page's billing form (`billing_form`), the ship-to names on a multi-recipient site (`recipients`).

## Which controls and values?
- Quantity and donation boxes: `quantity[^^__item_id^^]` with `value="^^__item_qty^^"` (a donation's becomes its amount box by `_pg_cart_retarget_qty_box()`). Buttons: `eo_submit_update`, `eo_submit_purchase`. `billing_salutation`, `billing_country` bound by `eo_field`; the nameless `#billing_same_as_shipping` gets its name.
- `pg_eo_fill_empty_selects()` fills an empty salutation or referral source list. The state text box and pick list keep their names (`pg_sa_state_list_fit()`).
- Values, flags and the page's settings: [[layout-type-converter-express-order-render]].

Related: docs/memory/designer/layout-type-converter-cart.md
