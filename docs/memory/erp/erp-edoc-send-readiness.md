---
name: erp-edoc-send-readiness
description: What the provider would refuse, asked before it is: missing-field lists and format helpers, the repair form, the not-ready filter, the final-consumer number, sales-only rules, GİB's date order.
---

## How are the provider's refusals caught before sending?
- Every rule the provider would refuse goes into the driver's `party_missing($party, $invoice)` (buyer) or `document_missing($invoice)` (the document: an internet sale's carrier) as field => label, and the label says why ("Zip Code (five digits)"). Asked before any request: a refused write spends quota and answers in the provider's words.
- Format rules use the registry helpers, not driver copies: `erp_edoc_tax_number_valid()` (VKN/TCKN check digits), `erp_edoc_postcode_valid()` (five digits in Turkey), `erp_edoc_person_name()` (a person, or a carrier with a TCKN, needs first name + surname; the last word is the surname).
- The same lists feed the invoice screen and the send (`erp_edoc_invoice_party_missing()`, `_document_missing()`), the account list filter "Not ready for e-documents" (`erp_accounts.php?filter=edoc`), the order card's invoice gate and the automatic invoice (`erp_edoc_account_missing()`), and the counter sale's warning: "ready" means one thing everywhere.

## How is a gap repaired?
- The invoice's repair form (`#edoc_fix`, `erp_edoc_invoice_party_fix()`) offers the missing boxes (`erp_edoc_fix_fields()`): buyer fields go on the account card AND the invoice's copy, the carrier only on the invoice; known carriers are offered (`erp_edoc_known_carriers()`). Only while nothing has gone (`erp_edoc_invoice_party_editable()`: `edoc_status` none/error, no external id); after that the copy is evidence.

## Who is a buyer without an identity number?
- On e-Arşiv the walk-in account or a person's card goes with `erp_edoc_final_consumer_tckn()` (`11111111111`), never written to the card (its blank is the truth). A company card without VKN is stopped: there a blank means missing data.

## Which rules are for sales only?
- The date order, Move to today, the send card and the buyer checks apply to `direction = 'sales'`; a purchase invoice is the supplier's, with the supplier's date. A new e-document rule gets the same gate.

## What is GİB's date order?
- GİB takes documents of one type (e-Fatura and e-Arşiv counted apart) only in date order. `erp_edoc_invoice_date_behind()` warns before sending, from the provider's refusal (the driver's `date_refusal()`; İşbaşı names the latest date) or from the store's record (`erp_edoc_latest_sent_date()` by expected kind).
- Repair while the invoice has not gone: Move to today (`erp_edoc_invoice_redate()`, `#edoc_date`): issue date, due date (same term) and the account movement's date in one transaction; the kept PDF is replaced. Not offered (`erp_edoc_invoice_redate_allowed()`) in a closed period, for a number of another year, a foreign-currency invoice (its rate is its date's) or one with a return.
