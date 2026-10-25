---
name: erp-parasut-legacy-stale-ids
description: Legacy Paraşüt buttons on an order keeping the id of a record deleted on Paraşüt's screen: read first, a 404 naming it as not found left out and its id cleared, any other answer refused (#1544)
---

## What went wrong? (#1544)
- Create Invoice refused an order holding `orders.parasut_invoice_id` (#1084), E-Shipment one holding `parasut_shipment_id` (#1407); E-Shipment read the order's invoice and Create Invoice named its shipment document (#1506). A record deleted on Paraşüt's screen left its id, which no screen cleared: the buttons were disabled; a read or an invoice naming it got 404. The order stayed refused.

## What does a press do with a stored id now?
- Read first: `parasut_stored_record_check($type, $id, $get)` (GET `sales_invoices/{id}` or `shipment_documents/{id}`), judged by `parasut_stored_record_state($read, $type)`:
  - 200 `kept`: as before (a second record refused; Create Invoice names the document; E-Shipment judges `shipment_included`).
  - `gone` only for a 404 with an error titled `Record was not found` whose detail starts with `SalesInvoice-` / `ShipmentDocument-` (the kind read): left out, the id cleared (`_parasut_order_forget_record()`, only while the column holds it), the screen told.
  - Anything else `unknown`: refused. Own id: "... could not be asked whether it is still there: <Paraşüt's words>" (one closing period dropped, `parasut_refusal_words()`); Create Invoice's read of the document likewise; E-Shipment's read of the invoice: its old sentence, the words likewise.
- The unsure mark `?` is never read (#1416 unchanged).

## Why not every 404?
- Seen live (2026-10-24): a company the token cannot reach answers 401 `No access to company: <n>`; a wrong path 404 `No route matches.`; only a missing record 404 `Record was not found`, detail `<Type>-<id>`. The settings box saves the number as typed, so clearing on any 404 or 401 would free a live id: Create Invoice would issue a second invoice (#1084), E-Shipment go unlinked beside an invoice with shipment (stock out twice, #1506).
- Left open: a number pointing at another company the user can reach (one of theirs mistyped, a demo to real switch) answers `Record was not found` for this company's ids too, so a press clears the id and writes a new record there. Right for a switch; after a typo is put right, the order holds the new id and the old record stays at the first company.

## Which press clears which column?
| Press | Gone at Paraşüt | Then |
|---|---|---|
| Create Invoice | own `parasut_invoice_id` | cleared, invoiced as never sent |
| Create Invoice | `parasut_shipment_id` | cleared, invoice with shipment (Paraşüt's default) |
| E-Shipment | own `parasut_shipment_id` | cleared, VKN/TCKN asked (the screen skips it once an id is kept), shipped anew |
| E-Shipment | `parasut_invoice_id` | cleared (the link returns `gone`), document unlinked |
| ERP send | `parasut_invoice_id` | cleared, the send goes on (erp-parasut-legacy-invoiced-order.md) |
- `parasut_exported` stays: the Excel export sets it too.

## What does the order screen show?
- On a finished sale both buttons stay pressable once sent (title kept); the confirm says the press asks Paraşüt first and creates anew only if it was deleted there. Each record left out comes back in the functions' `&$notices`; `view_order.php` passes `$parasut_notices` and shows each as a warning (`add_warning(h())`).

## How was it checked live? (demo company, 2026-10-24)
- Dev order 443. E-Shipment over a deleted document: notice, new document. Create Invoice naming a deleted document: notice, invoice with `shipment_included` true and no link, stock out once. Create Invoice over a deleted invoice: notice, new invoice. E-Shipment over a deleted invoice: notice, unlinked document. Record still there, or company 999999999 (401): refused, ids kept.

## Tests
- `tests/parasut_stale_ids_test.php`: the state, the read, the notices, the refusal words, the link's `gone`, the order of reads and writes, the guarded clear, the order screen.
