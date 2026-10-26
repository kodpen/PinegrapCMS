---
name: widget-entity-fields-order
description: W8 order and order line values in the order view and its receipt e-mail: the tokens, who sees them, custom field labels, refund words, the line's product values, the queries.
---

## Which order values does the order view offer?
- `__order_po_number`, `__order_custom_field_1` / `_2` with `__order_custom_field_1_label` / `_2_label`, `__billing_fax`, `__order_gift_card_discount`, `__order_currency`, `__order_paid_date`, `__order_cancelled_date`, `__order_cancellation_reason`, `__order_refund_status`, `__order_refunded_amount`, `__order_refunded_date`; yes/no `__order_is_tax_exempt` (a condition only). Editor group "More order details" (`SW_ORDER_ENTITY_TOKENS`); the binding dropdown skips the yes/no entry.
- Who sees them: the order view's own gate (the signed-in owner, the session that just placed it, the receipt e-mail) runs before; nothing else reads them.

## How are they read?
- `pg_sw_entity_order_read()` (`includes/fn/widgets_entity_fields.php`): one query on `orders`, each column probed with `pg_schema_has()` (paid, cancel and refund columns came with upgrades), only when the tree names a token (`pg_sw_entity_wanted('order', …)`). Values are escaped with `h()` and added with `+=`, so a renderer token of the same name wins.
- Dates go through the widget's own formatter (`format_stamp` in the context), so the long-month format gives translated month names.

## Where do the custom field labels come from?
- The billing information page this session passed (`$_SESSION['ecommerce']['billing_information_page_id']`), else the site's first billing information page; a label is empty when its value is. The order does not store which page it came through. The labels are page settings, not in the site translation: a designer who wants them translated types the label as node text.

## What does the refund status say?
- `refunded`: "Refunded"; `pending`, `failed`, `manual_required`: "Refund in progress", as the cancellation e-mail tells the customer (a failed refund is the shop's to fix). `__order_refunded_amount` also counts what returns gave back.
- Tax exemption reads yes only where tax and tax exemption are switched on, as on the classic receipt.

## Which order line values does the item loop offer?
- `__item_shipped_qty` (only where the line shows its shipped quantity; `0` shows), `__item_vat` (`order_items.tax_total`, empty on a database without it), `__item_payment_period` (words), `__item_payment_count`, `__item_first_payment_date` (subscriptions only), `__item_event_name`, `__item_event_date` (the reserved event, as the classic receipt prints it, through `get_calendar_event()`); yes/no `__item_added_by_offer`, `__item_discounted_by_offer`. Editor groups "More item details (loop)" (`SW_ORDER_LINE_ENTITY_TOKENS`) and "Item's product details (loop)" (the product's list, `SW_PRODUCT_ENTITY_TOKENS`).
- Read by the item query itself (`pg_sw_entity_select('order_line', 'oi')`, `('product', 'p')`) only when the tree names one; kept raw in `$items[...]['entity']`, escaped in the loop, and carried by the `order.items` list rows too. No line token begins with an older `__item_` token.
- Not done: the `order.items` list's token palette does not name them yet (a Custom binding fills).
