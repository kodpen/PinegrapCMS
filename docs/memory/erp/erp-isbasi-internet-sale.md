---
name: erp-isbasi-internet-sale
description: What an internet sale adds to the İşbaşı SimpleInv body: the e-Arşiv block, the carrier, the payment type codes, and the open test-environment refusal.
---

## What does an internet sale add?
- `eGovernmentInvoice {invoiceTypeForEinvoice: 3, eArchivePaymentDate, eArchivePaymentAgent, website, eArchivePaymentType}`, `sendingDate`, `shipmentAgentItem {name, surName, identifier, firmType}` (a TCKN carrier is a person: first name + surname). No carrier → refused before sending.
- `eArchivePaymentType` (`erp_edoc_isbasi_payment_type()`): 0 card, 1 EFT/transfer, 2 cash on delivery, 3 payment intermediary, 4 other, in the order of `erp_invoices.payment_method`; `settings.payment_type_codes` overrides.
- Open: the test environment refuses every internet-sale e-Arşiv ("Ödeme tarihi … zorunludur") in all five date formats tried; the driver sends ISO 8601 and Logo was asked.

Related: docs/memory/erp/erp-isbasi-invoice-payload.md
