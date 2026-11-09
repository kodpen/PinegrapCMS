---
name: erp-auto-invoice-edocument
description: An automatic invoice and the e-document: nothing sent until a queue worker exists; internet-sale fields read by the drivers from erp_invoices; ubl.php only reads; POS sales carry none
---

## Is the e-document sent with the automatic invoice?
- No: nothing processes `erp_edoc_queue`, so the send stays the order screen's next step (`erp-auto-invoice.md`). Once a queue worker exists, the send is queued right after the automatic invoice (plan approved 2026-10-19; the worker: `plans/erp-parasut-open-work.md`).
- The legacy Paraşüt order export (`includes/fn/parasut.php`) takes no part and stays as it is.

## Where do an internet sale's e-Arşiv fields come from?
- From the invoice row: `erp_invoices.is_internet_sale` and the VUK 509 fields the order bridge stores (`erp-vuk-509-fields.md`). Each driver reads them when it builds its request: İşbaşı `eGovernmentInvoice` (`erp-isbasi-internet-sale.md`), Paraşüt `internet_sale` and `shipment` on `POST e_archives` (`erp-parasut-driver.md`).
- `includes/erp/edoc/ubl.php` only reads incoming UBL (`erp-edoc-incoming-invoices.md`); no outgoing UBL is built here.
- A POS sale (`orders.type = local`) carries none: `erp_order_internet_sale()` answers 0.

## What is still open?
- The İşbaşı test environment refuses every internet-sale e-Arşiv on the payment date (`erp-isbasi-internet-sale.md`). No field is guessed to get past it (`erp-edoc-driver-layer.md`).
