---
name: erp-isbasi-invoice-payload
description: What goes to Isbasi on send_invoice: final-consumer rule, pre-send validation, SimpleInv body fields, internet-sale block, unsupported types.
---

## How is a final consumer (no ID number) sent?
- If the buyer has no ID number and is the walk-in account (`ERP_WALKIN_ACCOUNT_ID`) or a PERSON card, `tcknVkn` = `erp_edoc_final_consumer_tckn()` (`11111111111`) and `isPerson = true` are sent. A company card without VKN is STOPPED: there a blank means "missing data", not "anonymous buyer". The number is NOT written to the card.
- The rule lives in the registry because it is GİB's rule; the Paraşüt driver will use the same one.

## What is checked before sending?
- After the final-consumer rule, before calling İşbaşı: title, VKN/TCKN (10 or 11 digits), address, postal code, city, district. If something is missing, one Turkish message points to the account card; no write quota is spent and no call is logged.
- An issued invoice's copy does not change: after the card is filled the invoice must be re-issued.

## What is in the `send_invoice` body? (`erp_edoc_isbasi_invoice_payload`)
- `SimpleInv`; `customer` from the copy (`isPerson` = person card or 11 digits; for a person the last word is the surname); `invoiceDate` `Y-m-d H:i:s` (noon 12:00); `currency` TRY → "TL"; `vatIncluded=false`.
- Line: `price` = `unit_price/100`; with a `discountRate`, `discountValue` is 0; `postalCode` (not in the model but İşbaşı requires it; not added to the address a second time); `productDetail.itemCode` = `products.code` (SKU, 50 chars) else `PG-<id>`; `itemType` 1 product / 2 service; `unit` Turkish name (`erp_edoc_isbasi_unit_name`); withholding `withholding {code, rateText "7/10"}`; `vatExemptionCode`.
- Internet sale: `eGovernmentInvoice {invoiceTypeForEinvoice: 3, eArchivePaymentDate, eArchivePaymentAgent (payment method label), website}` + `sendingDate` + `shipmentAgentItem {name=carrier_title, identifier=carrier_vkn, firmType 11 digits → 0}`; no carrier → error.
- `eArchivePaymentType` is sent ONLY if `settings.payment_type_codes[payment_code]` exists.
- Return/proforma and any `invoice_type` other than SATIS → explicit error (no code in the document).
- Response `data {invoiceId, uuid, no}` → `external_id`, `gib_uuid`, `gib_number`; the service writes all three, so the PDF/UBL buttons open without a query.

Related: docs/memory/erp/erp-isbasi-poll-documents-screens.md
