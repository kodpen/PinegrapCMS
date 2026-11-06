---
name: erp-parasut-legacy-press-claim
description: The order screen's Paraşüt Create Invoice and E-Shipment run under one named lock per order, so two presses at once cannot write two sales invoices, e-documents or waybills (#7570)
module: parasut
---

## What went wrong? (#7570)
- Create Invoice read `orders.parasut_invoice_id` at its start and wrote the unsure mark only seconds later (token, kept-record checks, GİB lookup, buyer card), unconditionally. Two presses at once (two tabs, two operators) both passed, each POSTed `sales_invoices` and its e-Archive / e-Invoice; the order kept the id written last. E-Shipment had no mark at all: two `shipment_documents`, stock out twice.

## How is a press claimed now?
- `parasut_create_invoice()` and `parasut_create_shipment()` are wrappers: `parasut_order_press_claimed()` takes `GET_LOCK(parasut_order_press_lock_name(database, order), 0)` before the order is read and runs `_parasut_create_invoice_claimed()` / `_parasut_create_shipment_claimed()`; released in `finally`.
- One lock per order for both buttons: Create Invoice reads the shipment id and E-Shipment the invoice id, so they must not overlap either.
- A press that finds the lock held is refused before any read or write: "Another Paraşüt send of this order is running…".
- Why a named lock, not a conditional UPDATE of the column: the lock goes with the connection, so a process that dies leaves no claim; the unsure mark it may leave is settled by the next press (`parasut_order_invoice_find()`, erp-parasut-legacy-invoice.md), unchanged.
- Lock names are server-wide: the name carries `DB_DATABASE@DB_HOST`, hashed under 64 characters.
- The ERP's own send claims its row separately (`erp_edoc_invoice_send_claim_where()`); the buttons are shown only with the ERP's provider off.

## Tests
- `tests/parasut_press_claim_test.php`; source tests of the two functions read the `_claimed` bodies.
