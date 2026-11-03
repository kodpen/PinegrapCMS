---
name: erp-order-card-invoice-cancel-rules
description: The order documents card's invoice cancel (cash right with allocations, confirm), re-invoicing after a cancel (the note moves over) and its notices.
---
# Order card: cancelling and re-invoicing

## Who may cancel the invoice from the card?
- The cancel takes the invoice's allocations off first (`erp_settlement_remove()`), so with any allocation (gift cards left out, `erp_invoice_receipt_count()`) it needs `USER_MANAGE_ERP_CASH`, like the invoice screen's "remove allocation". Without it the step is `blocked` with the reason (`erp_order_flow_cancel_refusal()`), and `erp_order_flow_run()` refuses it too. Before, an ERP clerk without the cash right freed allocations here (#5231, PR #5868).

## Does the card's cancel button ask first?
- Yes: the step carries `confirm`, printed as `data-confirm-content` with the invoice screen's wording (`erp_order_flow_cancel_confirm()`). Before, one click cancelled for good (#5233).

## What happens to the delivery note when the invoice is written again?
- A note tied to no invoice or to a cancelled one (`erp_order_flow_waybill_takes_invoice()`) moves to the new invoice and `erp_waybill_write_back()` runs. Before, it stayed on the cancelled invoice and the new one had no shipment date (#5232).

## What do the card's notices say?
- Only what went onto the invoice: date, carrier or both (`erp_order_flow_written_back_message()`). A cancelled order with no invoice shows the e-document step as not made for it, not "made official once issued" (#5234).
