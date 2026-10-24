---
name: erp-parasut-legacy-stale-ids
description: Legacy Paraşüt buttons on an order keeping the id of a record deleted on Paraşüt's screen: read first, a 404 left out and its id cleared, any other answer refused (#PR)
---

## What went wrong? (#PR)
- Create Invoice refused an order holding `orders.parasut_invoice_id` (#1084), E-Shipment one holding `parasut_shipment_id` (#1407); E-Shipment read the order's invoice and Create Invoice named its shipment document (#1506). A record deleted on Paraşüt's screen left its id behind and no panel screen clears it: the buttons were drawn disabled, E-Shipment refused on the 404 of its read, and an invoice naming a deleted document got 404 and was not written. The order stayed refused for good.

## What does a press do with a stored id now?
- It reads the record first: `parasut_stored_record_check($type, $id, $get)` (GET `sales_invoices/{id}` or `shipment_documents/{id}`), judged by `parasut_stored_record_state()`:
  - 200 `kept`: as before (a second record refused; Create Invoice names the document; E-Shipment judges `shipment_included`).
  - 404 `gone` ("Record was not found"): left out, the id cleared (`_parasut_order_forget_record()`, only while the column still holds that id), the screen told.
  - Anything else (no token, timeout, 5xx, 401/403, 429) `unknown`: refused, nothing guessed. Own id: the old refusal; Create Invoice's read of the document: "could not be asked whether ... is still there"; E-Shipment's read of the invoice: its old refusal.
- The unsure mark `?` is never read (#1416 unchanged).

## Which press clears which column?
| Press | Gone at Paraşüt | Then |
|---|---|---|
| Create Invoice | own `parasut_invoice_id` | cleared, invoiced as never sent |
| Create Invoice | `parasut_shipment_id` | cleared, invoice as without shipment (Paraşüt's default) |
| E-Shipment | own `parasut_shipment_id` | cleared, VKN/TCKN asked (the screen skips it for an order with an id), shipped as never shipped |
| E-Shipment | `parasut_invoice_id` | cleared (`parasut_shipment_invoice_link()` returns `gone`), document unlinked |
- `parasut_exported` stays: the Excel export sets it too.

## What does the order screen show?
- Both buttons stay pressable once sent (never `disabled`). A sent one keeps its badge and title and its confirm says the press asks Paraşüt first and creates a new record only if it was deleted there. Each record left out is a notice beside the outcome: both functions fill `&$notices`, `view_order.php` adds them with `h()`.

## How was it checked live? (demo company, 2026-10-24)
- Branch code on dev order 443 (441 is refused before any request: its line falls back to 18%). A deleted record answers GET 404 `Record was not found`, detail `ShipmentDocument-<id>` / `SalesInvoice-<id>`.
- E-Shipment over a deleted document: notice, new document. Create Invoice naming a deleted document: notice, invoice with `shipment_included` true and no link, stock out once. Create Invoice over a deleted invoice: notice, new invoice. E-Shipment over a deleted invoice: notice, unlinked document. With the record still there each press was refused as before. All records deleted and the product's tracking put back; the demo matched its baseline.

## Tests
- `tests/parasut_stale_ids_test.php`: the state, the read, the notices, the link's `gone`, the order of reads and writes in both functions, the guarded clear, the order screen.
