---
name: trendyol-invoiced-push
description: Trendyol Invoiced status push: "Mark invoiced" form on the Imported orders card, PUT shipment-packages with params.invoiceNumber, accept first, remote_status written, no schema
---

## Which call?
- `PUT /integration/order/sellers/{sellerId}/shipment-packages/{packageId}`, the same call as accepting (Picking). Body `{"lines":[{"lineId","quantity"}],"params":{"invoiceNumber":"…"},"status":"Invoiced"}` (`mp_trendyol_invoiced_body()`): every line of the package, line ids as JSON numbers after `mp_trendyol_id_fits()`.
- Docs: developers.trendyol.com/docs/paket-statü-bildirimi-updatepackage (updatedAt 2026-01-22), read 2026-10-30. They say: only Picking and Invoiced can be sent; Picking must come before Invoiced; `invoiceNumber` only for Invoiced. Their example number is `EME2018000025208`; no format, response body or error status is documented.
- `mp_trendyol_mark_invoiced()`, capability `mark_invoiced`. Any 2xx is success (`$allow_empty`). A 4xx shows their bare `message` when the shared reader found none (as the invoice link); 429 and 5xx keep `retry`. No 409 handling: none is documented.

## What is checked before the call?
- `mp_trendyol_invoice_number_problem()`: letters, digits and hyphens, first a letter or digit, at most 50 (our cap; the docs give none). A link or a number with spaces is refused.

## Which rows show the form?
- `mp_invoiced_offer($row, $stage)` (`includes/api/outbound/orders.php`): `imported`, with an order, `mp_order_stage()` `open`, `remote_status` not `Invoiced`; plus `mp_can(…, 'mark_invoiced')` and the pull capability. The folded "Mark invoiced" form sits under "Send invoice link" (`mp_settings_invoiced_form()`, action `mark_invoiced`).

## What happens on submit?
- A package still `Created` and not approved is accepted first (`approve_lines`), written `remote_status = 'Picking', approved = 1` as the label request does; a refused accept stops there.
- Success: `remote_status = 'Invoiced', approved = 1`, an activity-log line with the invoice number, a notice. No schema: the next order read overwrites `remote_status` with theirs. `invoice_sent` is not used: it means the link was accepted.
- A refusal is a screen warning only, never `last_error` (that would drop the package from the own-carrier shipping pass, which takes `Created`, `Picking` and `Invoiced`).

## What is unverified?
- No live call. The success body; the 400 body for a package not Picking, already Invoiced or past it; whether every line must be sent (the docs' examples send lines, as Picking does).
- Whether Trendyol checks the number against GİB e-documents.

Related: docs/memory/commerce/trendyol-invoice-link.md, docs/memory/commerce/trendyol-orders.md
