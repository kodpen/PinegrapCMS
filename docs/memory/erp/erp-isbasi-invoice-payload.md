---
name: erp-isbasi-invoice-payload
description: What goes to İşbaşı: draft then hand-over (integrationInvoices, eInvoiceWithJson), the final consumer, pre-send checks, SimpleInv fields, invoice type codes, the internet sale.
---

## How does an invoice become official at İşbaşı?
- `send_invoice`: `POST /api/v1.0/invoices/integrationInvoices` (SimpleInv) creates a DRAFT; `data {invoiceId, uuid, no}` gives `external_id`, `gib_uuid`, `gib_number` at once (PDF/UBL need no query); status `created`.
- `submit_invoice`: `POST /api/v1.0/einvoices/eInvoiceWithJson?invoiceId={id}`, no body, makes it official (Logo, 2026-09-21). A hand-over that worked can answer HTTP 400 + `isError: true` + one word ("İşlendi", "İmzalandı"), so no words are trusted: on a non-OK answer the document is read once (`poll`) and success is "no longer an unsent draft".
- e-Fatura or e-Arşiv is İşbaşı's choice from the buyer's number (series REE… / REC…).

## Who is a buyer without an identity number?
- The walk-in account (`ERP_WALKIN_ACCOUNT_ID`) or a person's card → `tcknVkn` = `erp_edoc_final_consumer_tckn()` (`11111111111`), `isPerson = true`, not written to the card. A company card without VKN is stopped (owner decision, 2026-09-21).

## What is checked before sending? (`erp_edoc_isbasi_party_missing()`)
- Title, VKN/TCKN (10 or 11 digits, check digits), a person's first name + surname, address, postcode (five digits), city, district: each learnt from a 400 of the test environment. A gap → one message pointing to the account card; no quota spent, nothing logged. An issued invoice's copy keeps what it was issued with: repair it while unsent (erp-edoc-send-readiness.md).

## What is in the SimpleInv body? (`erp_edoc_isbasi_invoice_payload()`)
- `customer` from the copy: `code` = the linked İşbaşı card's code; `isPerson` = person card or 11 digits (last word = surname); `postalCode`, not in the model but required. `invoiceDate` `Y-m-d 12:00:00`; `currency` TRY → "TL"; `vatIncluded=false`.
- Line: `price` = `unit_price/100`; with a `discountRate`, `discountValue` is 0; `productDetail.itemCode` = `products.code` (SKU, 50) else `PG-<id>`; `itemType` 1 product / 2 service; `unit` Turkish name (`erp_edoc_isbasi_unit_name`); withholding `{code, rateText "4/10"}`; `vatExemptionCode`.
- Types (`erp_edoc_isbasi_government_type()`, İşbaşı's own list, read 2026-09-23): SATIS sends none, ISTISNA `eGovernmentType` 2, TEVKIFAT 4. OZELMATRAH (needs a base per line), IADE, IHRACAT are refused with the reason; so are a return document (İşbaşı takes a sales return only on its own screen) and a proforma.

## What does an internet sale add?
- `eGovernmentInvoice {invoiceTypeForEinvoice: 3, eArchivePaymentDate, eArchivePaymentAgent, website, eArchivePaymentType}`, `sendingDate`, `shipmentAgentItem {name, surName, identifier, firmType}` (a TCKN carrier is a person: first name + surname). No carrier → refused before sending.
- `eArchivePaymentType` (`erp_edoc_isbasi_payment_type()`): 0 card, 1 EFT/transfer, 2 cash on delivery, 3 payment intermediary, 4 other, in the order of `erp_invoices.payment_method`; `settings.payment_type_codes` overrides.
- Open: the test environment refuses every internet-sale e-Arşiv ("Ödeme tarihi … zorunludur") in all five date formats tried; the driver sends ISO 8601 and Logo was asked.

Related: docs/memory/erp/erp-isbasi-poll-documents-screens.md
