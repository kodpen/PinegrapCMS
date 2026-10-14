---
name: erp-vuk-509-fields
description: VUK 509 e-Arsiv fields on erp_invoices: what the order bridge derives for each, where orders.paid_at comes from, and the carrier columns.
---

## Which fields does the bridge derive?
Fields e-Arşiv treats as mandatory live on `erp_invoices` and `erp_invoice_from_order()` derives them from the order. A return document COPIES five of them from the parent invoice, it does not re-derive.

| Field | Source |
|---|---|
| `is_internet_sale` | `orders.type`: `local` → 0, `online` / `marketplace` → 1 |
| `web_address` | `ERP_WEB_ADDRESS`; if empty `URL_SCHEME . HOSTNAME` |
| `payment_method` | `erp_payment_method_code()` → GİB `OdemeSekli` code: `KREDIKARTI/BANKAKARTI`, `ODEMEARACISI`, `EFT/HAVALE` |
| `payment_date` | `erp_order_payment_date()` → `orders.paid_at`; else if `transaction_id` is filled, `order_date` |
| `shipment_date` / `carrier_title` / `carrier_vkn` | `erp_order_shipment()` → earliest non-zero `ship_tos.ship_date` (if not later than today); carrier from that recipient's shipping method, title falls back to the method name |

## What are the supporting rules?
- `orders.paid_at` is written by `submit_order.php` ONLY for a gateway-confirmed payment (`transaction_id` filled); marketplace import writes `placed_at`.
- `shipping_methods.carrier_title` / `carrier_vkn` are edited on the shipping-method screens; VKN is digits only, at most 11.
- Former gap (offline orders such as bank transfer had no `paid_at` source and an empty `orders.payment_method`) was closed by the awaiting-payment flow: see erp-awaiting-transfer-orders.

Related: docs/memory/erp/erp-awaiting-transfer-orders.md
