---
name: erp-parasut-legacy-order-gates-limits
description: What the order gates of #1628 do not cover: an ERP invoice whose Paraşüt record was deleted there, and orders whose "Sent" flag was set by a failed e-document before 2026.4.4
---

## What if the ERP invoice's record is deleted on Paraşüt's screen?
- Create Invoice stays refused. `parasut_order_erp_invoice()` reads the record kept on the ERP invoice (`edoc_external_id` or `parasut_invoice_id`) and asks Paraşüt nothing, so the deletion is not seen, and E-Shipment's 404 clears nothing on the ERP's row.
- The order is free again only once its ERP invoice is cancelled: the read skips a `cancelled` invoice.

## Can an old order show "Sent" after a failed e-document?
- Yes, an order pressed before 2026.4.4. Up to 2026.4.3 Create Invoice set `orders.parasut_exported` with the sales invoice id even when the e-Invoice / e-Archive POST failed. Since 2026.4.4 only a finished conversion sets it, but the flag already stored stays, and #1628's badge reads "Sent" from it.
- Paraşüt's own record is the answer for such an order. The flag is cleared only when the sales invoice is found deleted at Paraşüt (#1660).

## Where are the gates themselves?
- `erp-parasut-legacy-order-gates.md`.
