---
name: erp-parasut-legacy-order-gates
description: Which orders the order screen's Paraşüt Create Invoice and E-Shipment go for (a finished sale, not one the ERP invoiced through a provider), and when the status row says "Sent" (#1628)
---

## Which statuses do the buttons go for? (#1628)
- The ERP's billable ones only (`erp_order_billable_statuses()`: complete, exported), through `parasut_order_billable_statuses()`: the ERP code loaded as Create Invoice loads it, else the same two.
- `parasut_order_status_refusal()` says it in the ERP's words: "A cancelled order is not invoiced." / "An order is invoiced once it is complete."; E-Shipment the delivery note's two.
- Asked by `parasut_create_invoice()` and `parasut_create_shipment()` right after the order is read (`parasut_order_press_refusal()`), by the screen's post before the contact and VKN/TCKN checks, and when drawing: no buttons or forms for another status. The status rows stay.
- Before, a cancelled order (its total kept, so the total check passed) got a sales invoice and its e-document at GİB, E-Shipment counted its goods out, and a checkout never finished but holding its buyer (`contact_id` is set before the order is placed) could be shipped.

## And an order the ERP already invoiced? (#1628)
- `parasut_order_erp_invoice()` reads the order's ERP sales invoice that is not cancelled (`erp_invoice_for_order()`'s rule). `parasut_order_erp_invoice_record()`: `parasut` (`edoc_provider` parasut with `edoc_external_id`, or the driver's `parasut_invoice_id`), `unsure` (the driver's `?` mark), `other` (another provider's record), `none` (none, or on paper).
- Create Invoice refuses every state but `none` before any request; the screen draws no Create Invoice and the status row says "Invoiced through the ERP" with the number and Paraşüt id. `orders.parasut_invoice_id` never holds the ERP's record, so once the provider or the ERP was switched off the button offered a second invoice and e-document.
- E-Shipment judges the ERP's Paraşüt record as the order's own invoice (`parasut_shipment_invoice_id()`, then the link of #1506): with shipment refused, without named. The order's own id comes first; the ERP's `unsure` refuses; a 404 clears nothing on the ERP's row.
- `other` is refused too: the sale's e-document is at GİB already. An ERP invoice on paper stops neither button (the ERP without a provider leaves the e-document to them).

## When does the status row say "Sent"? (#1628)
- The sales invoice's id is kept after a failed, rejected or still running conversion; only a finished one sets `orders.parasut_exported`. `parasut_order_invoice_status_badge($value, $in_button, $edoc_confirmed)` says "Sent" only with the flag (default off); else "Sales invoice created, e-document not confirmed" (button: "E-document not confirmed"), in warning colours.
- Limits: the spreadsheet export sets the flag too; a conversion finished after the press's six-second poll stays unconfirmed (its job id is not kept). Paraşüt has the last word.

## How was it checked?
- Unit tests only: the demo key expired on 2026-10-25. The link's read of the driver's invoice uses the shape read live on 2026-10-24 (a driver invoice naming a shipment document: `shipment_included` false).

## Tests
- `tests/parasut_order_gates_test.php`: statuses and words, the ERP record and its refusals, E-Shipment's invoice and link, the order of checks in both writers, the badges, the order screen.
