---
name: api-order-money-fields
description: The external API's order totals, the gift card and instalment amounts, and payment_installment
---

# Order money fields in the external API

## Which totals does an order answer carry?
`api_order_money_fields()` in `includes/api/resources/orders.php`: `subtotal`, `discount`, `tax`, `shipping`, `surcharge`, `gift_card_discount`, `installment_charges`, `total`, all under `totals`, integer minor units as stored (`round()` then `api_money()`), 0 when the row lacks the column. The OpenAPI `Order` object follows from `api_order_schema()`. GET /orders, GET /orders/{id}, the update and ship answers all use the same select and presenter.

## Why the gift card and instalment amounts?
Neither has a line of its own but both change `total`: a gift card lowers it (`submit_order.php`), an instalment payment raises it (PayTR and iyzico write `installment_charges` and raise `orders.total`, commerce `payment-driver-paytr-installments.md`). Without them a client could not explain why the rows did not add up to the total, while the panel's order screen shows both rows. Added for issue #3413.

## What is payment_installment?
A top-level integer: the number of instalments the card was charged in. 1 for a single payment (the column default); 0 when the stored value is empty (an order saved before a payment was taken).
