---
name: trendyol-invoice-link
description: Trendyol invoice link of an imported package: typed by the operator on the Imported orders card, the seller-invoice-links call, link checks, 409, invoice_sent, no removal
---

## Why does the operator type the link?
- The ERP e-document PDF is served only behind the panel login, so the shop has no address the buyer could open; no job sends invoices. Each imported row on the Imported orders card (`marketplace_settings.php`, action `invoice_link`) has a folded "Send invoice link" form.
- The form shows only when the connector can (`mp_can(…, 'send_invoice_link')`), `mp_invoice_sent_ready()` is true, the row is `imported` with an order, `invoice_sent` is 0 and `mp_order_stage()` is not `cancelled`. A sent row shows the "Invoice sent" pill.

## Which call?
- `POST /integration/sellers/{sellerId}/seller-invoice-links`, body `{"invoiceLink","shipmentPackageId"}` (`mp_trendyol_invoice_link_body()`, id as a JSON number after `mp_trendyol_id_fits()`). Not under `/order/`. Docs: developers.trendyol.com/reference/sendinvoicelink, modified 2026-02-03, read 2026-10-26.
- `invoiceNumber` (16 chars, `^[A-Za-z0-9]{3}(20[2-9][0-9])\d{9}$`) and `invoiceDateTime` (10 or 13 digits) are required only for micro export packages: not sent.
- 201 with no documented body: any 2xx is success (`$allow_empty`).
- 409: an invoice already exists for the package, or the link was given to another package. Named in our own message (`mp_trendyol_send_invoice_link()` watches the status). Their error body is `{error, message, timestamp, path, status}`, which `mp_trendyol_error_message()` does not read, so a 4xx `message` is taken there.
- The link must stay reachable ten years (their legal note), shown in the form's help.

## What is checked before the call?
- `mp_trendyol_invoice_link_problem()`: http(s), a host without `@` or spaces, at most 1000 bytes (our cap; none documented). An invoice number typed instead of a URL is refused.

## What is written?
- Success: `invoice_sent = UNIX_TIMESTAMP()` where it is still 0, an activity-log line with the link, a notice. A refusal is a screen warning only, never `last_error` (that would drop the package from the own-carrier shipping pass).
- No `Invoiced` status push and no webhook event for it.

## What is not built?
- Changing or removing a link: their delete call needs the buyer's customerId, which the import does not keep. A wrong link is fixed in the seller panel.

## What is unverified?
- No live call. The 201 body; the exact 400/409 bodies; whether a package must be Picking/Invoiced first; whether Trendyol fetches the link (a PDF reachable only from some networks).

Related: docs/memory/commerce/trendyol-orders.md, docs/memory/commerce/marketplace-commission-and-invoice-columns.md
