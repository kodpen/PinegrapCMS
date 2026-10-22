---
name: erp-vuk-509-fields
description: VUK 509 e-Arsiv fields on erp_invoices: what the order bridge derives for each, where orders.paid_at comes from, the carrier columns, and what a return copies.
---

## Which fields does the bridge derive?
Fields e-Arşiv treats as mandatory live on `erp_invoices`. `erp_order_sale_fields()` derives them from the order, for `erp_invoice_from_order()` and for the order screen's own Paraşüt button, which sends them with its e-document (#1278, erp-parasut-legacy-order-buttons.md). A return document COPIES all of them from the parent invoice (`returns.php`), it does not re-derive.

| Field | Source |
|---|---|
| `is_internet_sale` | `erp_order_internet_sale()`: `orders.type` `local` → 0, anything else (web, marketplace, older rows) → 1 |
| `web_address` | the same, internet sale only: `ERP_WEB_ADDRESS`; if empty `URL_SCHEME . HOSTNAME` |
| `payment_method` | `erp_order_payment_method_code()`: `DIGER` for an order put on the account (`orders.erp_on_account`, 10.60), else `erp_payment_method_code()` → GİB `OdemeSekli`: card `KREDIKARTI/BANKAKARTI`, PayPal and Pay With Iyzico `ODEMEARACISI`, Offline Payment `EFT/HAVALE`, unknown '' |
| `payment_date` | `erp_order_payment_date()` → `orders.paid_at`; else, with `transaction_id` filled, `order_date` |
| `shipment_date` / `carrier_title` / `carrier_vkn` | `erp_order_shipment()`: earliest non-zero `ship_tos.ship_date` of a complete recipient, if not later than today; carrier from that recipient's shipping method, or before anything ships from the first recipient with a method; title falls back to the method name |

## What are the supporting rules?
- `orders.paid_at` is written by `submit_order.php` for a gateway-confirmed payment (`transaction_id` filled) and by `pg_order_mark_paid()` when a transfer is marked received; marketplace import writes `placed_at`. Before that, offline orders had no `paid_at` source: see erp-awaiting-transfer-orders.
- `shipping_methods.carrier_title` / `carrier_vkn` are edited on the shipping-method screens; VKN is digits only, at most 11.
- A waybill fills an empty shipment date and carrier on its invoice later (`erp_waybill_write_back()`, erp-local-sale); a filled field is never overwritten.

Related: docs/memory/erp/erp-awaiting-transfer-orders.md
