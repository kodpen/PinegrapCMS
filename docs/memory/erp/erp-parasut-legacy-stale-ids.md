---
name: erp-parasut-legacy-stale-ids
description: Legacy Paraşüt buttons on an order keeping the id of a record deleted on Paraşüt's screen: read first, a 404 naming it as not found left out and its id cleared, any other answer refused (#PR)
---

## What went wrong? (#PR)
- Create Invoice refused an order holding `orders.parasut_invoice_id` (#1084), E-Shipment one holding `parasut_shipment_id` (#1407); E-Shipment read the order's invoice and Create Invoice named its shipment document (#1506). A record deleted on Paraşüt's screen left its id behind, which no panel screen clears: the buttons were drawn disabled, E-Shipment refused on the 404 of its read, and an invoice naming a deleted document got 404 and was not written. The order stayed refused for good.

## What does a press do with a stored id now?
- It reads the record first: `parasut_stored_record_check($type, $id, $get)` (GET `sales_invoices/{id}` or `shipment_documents/{id}`), judged by `parasut_stored_record_state($read, $type)`:
  - 200 `kept`: as before (a second record refused; Create Invoice names the document; E-Shipment judges `shipment_included`).
  - `gone` only for a 404 with an error titled `Record was not found` whose detail starts with `SalesInvoice-` / `ShipmentDocument-` (the kind read): left out, the id cleared (`_parasut_order_forget_record()`, only while the column still holds it), the screen told.
  - Anything else `unknown`: refused. Own id: "already has ... could not be asked whether it is still there: <Paraşüt's words>"; Create Invoice's read of the document: "could not be asked whether ... is still there"; E-Shipment's read of the invoice: its old refusal.
- The unsure mark `?` is never read (#1416 unchanged).

## Why not every 404?
- A wrong company number can answer 404 too: `{"status":404,"error":"Not Found"}` (seen for no such company and one not the user's; the demo's code-grant token got 401 `No access to company`); a wrong path `Not Found` / `No route matches.`. The settings box saves the number as typed and a password-grant token still works, so a press would clear a live id; once corrected, Create Invoice would issue a second invoice (#1084) and E-Shipment would go unlinked beside an invoice with shipment (stock out twice, #1506).

## Which press clears which column?
| Press | Gone at Paraşüt | Then |
|---|---|---|
| Create Invoice | own `parasut_invoice_id` | cleared, invoiced as never sent |
| Create Invoice | `parasut_shipment_id` | cleared, invoice with shipment (Paraşüt's default), as for an order never shipped |
| E-Shipment | own `parasut_shipment_id` | cleared, VKN/TCKN asked (the screen skips it once an id is kept), shipped as never shipped |
| E-Shipment | `parasut_invoice_id` | cleared (the link returns `gone`), document unlinked |
- `parasut_exported` stays: the Excel export sets it too.

## What does the order screen show?
- Both buttons stay pressable once sent; a sent one keeps its badge and title, and its confirm says the press asks Paraşüt first and creates a new record only if it was deleted there. Each record left out is a notice (`&$notices`, shown with `h()`).

## How was it checked live? (demo company, 2026-10-24)
- Branch code on dev order 443 (441 is refused before any request: its line falls back to 18%). A deleted record answers GET 404 `Record was not found`, detail `ShipmentDocument-<id>` / `SalesInvoice-<id>`.
- E-Shipment over a deleted document: notice, new document. Create Invoice naming a deleted document: notice, invoice with `shipment_included` true and no link, stock out once. Create Invoice over a deleted invoice: notice, new invoice. E-Shipment over a deleted invoice: notice, unlinked document. Record still there, or the read sent to a company that does not exist: refused, ids kept. All records deleted, the product's tracking put back; the demo matched its baseline.

## Tests
- `tests/parasut_stale_ids_test.php`: the state (the 404 shapes above), the read, the notices, the link's `gone`, the order of reads and writes, the guarded clear, the order screen.
