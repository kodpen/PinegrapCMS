---
name: order-preview-widget
description: The Order Preview widget (last classic checkout step): values from get_order_preview()'s custom-layout branch, page row, controls by name, sections, what is left out.
---

## What is it?
- Kind `order_preview` (`includes/fn/widgets_order_preview.php`), the fourth and last classic checkout widget ([[system-widget-missing-types]]). It posts to `order_preview.php` (gift card apply, else `submit_order('order preview')`) with the classic names; the checks, charge, receipt and redirects are unchanged.

## Where do the totals come from?
- From `get_order_preview()` itself: with `layout_data_only` it runs its custom-layout branch and returns the array it would hand `render_layout()` (form included) instead of drawing. So every figure, payment method, default method, surcharge and installment table is the classic computation (owner decision 2026-10-19, `workflow/decisions-made-product.md`); the widget computes nothing.
- The tree's HTML, wrapped in the form with the branch's `system` hidden fields and scripts, goes through the branch's liveform `prepare()`: the posted `total` / `total_with_surcharge`, a single method's hidden `payment_method`, required marks and kept values.
- Tokens use the cart's names (`__cart_subtotal` … `__cart_total_with_surcharge`, `__cart_total_charged`, `__currency_disclaimer`) and the order view's billing names (`__billing_name`, `__billing_address_html` …).

## Where are the page's settings?
- In the page's `order_preview_pages` row, made at render (`INSERT IGNORE`); `pg_order_page_known($id, 'order preview')` takes a page of the classic type or one with a row. Settings `next_page_id`, `terms_page_id`, `card_verification_number_page_id`, `offline_payment_always_allowed`, `offline_payment_label`, `order_receipt_email_page_id` are written only when the widget has the key (`pg_op_page_row_changes()`); a receipt page turns the receipt e-mail on as HTML, as the express order widget does.

## Which controls and parts?
- By name: `payment_method` radios (values `Credit/Debit Card`, `PayPal Express Checkout`, `Pay With Iyzico`, `Offline Payment`), `card_number` (id too: the installment script finds it by id), `expiration`, `card_verification_number`, `threedsecure`, `gift_card_code` + `submit_apply_gift_card`, `terms`, `submit_purchase_now` (the legal documents' boxes go in front of it, `designer.php` wraps the render as for the express order).
- Flags: `pg_op_flags()`; a part off drops its controls too. One method: radios dropped, the branch's hidden field posts it. `software.init_payment_method()` (in `system`) makes the card fields required only while the card is chosen.
- Sections (not designable): `order_recipients`, `applied_offers`, `applied_gift_cards`, `recurring_payments`, `billing_form`, `installment_table`, `card_installments` (the express order widget's `_eo_render_installment_box()`).

## What is left out?
- "On account" (ERP) and the hosted card fields: the classic custom layout offers neither. The currency switcher. Hiding the card fields while another method is chosen, and the surcharge rows' switch (the classic script hangs them on classes): both rows show, with the surcharge note.
- A background render draws nothing (the visitor's own order).
