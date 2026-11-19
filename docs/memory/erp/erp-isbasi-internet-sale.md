---
name: erp-isbasi-internet-sale
description: What an internet sale adds to the İşbaşı SimpleInv body: the e-Arşiv block, the payment date format, the carrier and its title, the payment type codes.
---

## What does an internet sale add?
- `eGovernmentInvoice {invoiceTypeForEinvoice: 3, eArchivePaymentDate, eArchivePaymentAgent, website, eArchivePaymentType}`, `sendingDate`, `shipmentAgentItem {name, surName, identifier, firmType}`. No carrier → refused before sending (`erp_edoc_isbasi_document_missing()`).
- `eArchivePaymentType` (`erp_edoc_isbasi_payment_type()`): 0 card, 1 EFT/transfer, 2 cash on delivery, 3 payment intermediary, 4 other, in the order of `erp_invoices.payment_method`; `settings.payment_type_codes` overrides.

## In which format does the payment date go?
- The date alone, `Y-m-d` (the invoice's payment date, else its issue date). The test environment refuses it with a time (`…T12:00:00`), as ISO with or without an offset or milliseconds, and as `dd.MM.yyyy`, with "Ödeme tarihi … zorunludur" (2026-09-23 and 2026-11-17). İşbaşı stores it as `…T00:00:00+00:00`.
- Until 2026-11-17 the driver sent `Y-m-d\TH:i:s`, so every internet-sale e-Arşiv was refused; the date alone was sent from dev and signed (series INX, test environment).

## How does the carrier go?
- An 11-digit number is a person (`firmType 0`): `name` + `surName` from the title, which needs two words. A 10-digit number is a company (`firmType 1`): `name` and `title` both carry the title; without `title` İşbaşı answers "Kurumsal firma için ünvan gereklidir." (test environment, 2026-11-17).
- İşbaşı matches the carrier card by its identifier: when a card with that number exists, the document shows the card's title, not the one sent.

Related: docs/memory/erp/erp-isbasi-invoice-payload.md, docs/memory/erp/erp-edoc-send-readiness.md
