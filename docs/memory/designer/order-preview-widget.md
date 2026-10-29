---
name: order-preview-widget
description: The Order Preview widget (last classic checkout step): values from get_order_preview()'s custom-layout branch, page row, controls by name, sections, what is left out.
module: widgets_order_preview
---

## What is it?
- Kind `order_preview` (`includes/fn/widgets_order_preview.php`), the last classic checkout widget ([[system-widget-missing-types]]). Posts to `order_preview.php` (gift card apply, else `submit_order('order preview')`) with the classic names; checks, charge, receipt, redirects unchanged.

## Where do the totals come from?
- From `get_order_preview()`: with `layout_data_only` its custom-layout branch returns the array it would hand `render_layout()` (form included). So every figure, payment method, default method, surcharge and installment table is the classic computation (owner decision 2026-10-19, `workflow/decisions-made-product.md`).
- The tree's HTML, wrapped in the form with the branch's `system` hidden fields and scripts, goes through the branch's liveform `prepare()`: the posted `total` / `total_with_surcharge`, a single method's hidden `payment_method`, required marks and kept values.
- Tokens: the cart's names (`__cart_subtotal` …) and the order view's billing names (`__billing_name` …).

## Where are the page's settings?
- In the page's `order_preview_pages` row, made at render (`INSERT IGNORE`); `pg_order_page_known($id, 'order preview')` takes a page of the classic type or one with a row. Settings `next_page_id`, `terms_page_id`, `card_verification_number_page_id`, `offline_payment_always_allowed`, `offline_payment_label`, `order_receipt_email_page_id` are written only when the widget has the key (`pg_op_page_row_changes()`); a receipt page turns the HTML receipt e-mail on.

## Which controls and parts?
- By name: `payment_method` radios (values `Credit/Debit Card`, `PayPal Express Checkout`, `Pay With Iyzico`, `Offline Payment`), `card_number` (id too: the installment script finds it by id), `expiration`, `card_verification_number`, `threedsecure`, `gift_card_code` + `submit_apply_gift_card`, `terms`, `submit_purchase_now` (legal documents' boxes before it, from `designer.php`).
- Flags: `pg_op_flags()`; a part off drops its controls too. One method: radios dropped, the branch's hidden field posts it. `software.init_payment_method()` (in `system`) makes the card fields required only while the card is chosen.
- With 2+ methods, `pg_op_stamp_method_classes()` stamps the classes that script switches, found by bindings: `credit_debit_card` on the `pays_by_card` node holding `card_number` (not the card's radio row); with a surcharge `surcharge_row` / `surcharge_total_row` on the `has_surcharge` rows and `total_row` on the outermost node holding `__cart_total` alone. `.purchase_button` is not stamped: without `data-paypal-label` the script would blank its label.
- Sections (not designable): `order_recipients`, `applied_offers`, `applied_gift_cards`, `recurring_payments`, `billing_form`, `installment_table`, `card_installments` (the express order widget's `_eo_render_installment_box()`).

## How do the payment service methods and On Account get a radio?
- Saved trees have rows only for card, PayPal Express, iyzico, Offline. With 2+ methods `pg_op_add_method_rows()` adds one per driver method (`$d['payment_driver_methods']`, before Offline) and On Account (last): copies of the tree's card or Offline row, value, id `op_pm_<value>` and label set, flag removed; no new flag or token (#3126).
- On Account: `pg_op_offer_on_account()` (`pg_erp_open_account_option()`): never with a recurring line (`submit_order.php` refuses it), only beside another method. Counted in `number_of_payment_methods`; making one method two drops the hidden `payment_method` from `$d['system']` (`init_payment_method()` reads it before the radios).
- Copies have no `pays_by_card` flag, so card stamping is unchanged.

## What is left out?
- Nothing: hosted card fields come with `$d['system']` (`commerce/payment-hosted-classic-pages.md`); currency picker [[checkout-currency-picker]].
- A background render draws nothing.
