---
name: accounting-sync-census-xero-sale
description: QuickBooks and Xero sync census: what Xero takes for a sale (invoice, payment, credit note, contact, item), tax types, currency, Idempotency-Key, pages not read
---

## What does Xero take for a sale?
- D = `https://developer.xero.com/documentation/`; base `https://api.xero.com/api.xro/2.0`; (S) = from search excerpts of the page named.
- Invoice: `PUT` or `POST /Invoices`, Type `ACCREC`, a Contact (ContactID) and lines with `ItemCode`, `AccountCode`, `TaxType`; without TaxType the account's default rate is used (S, D `api/accounting/invoices`). No line cap is stated (S).
- A paid web order: the invoice as AUTHORISED, then `PUT /Payments` with the invoice, an account (type BANK or `EnablePaymentsToAccount`), a date and an amount; Xero advises a clearing account per payment type (S, D `api/accounting/payments`, D `guides/how-to-guides/handling-payment-processor-receipts-in-xero`).
- Refund: a credit note (Type `ACCRECCREDIT`), AUTHORISED, then allocated with `PUT /CreditNotes/{id}/Allocations` (not in the same call), or paid out through Payments with its CreditNoteID (S, D `api/accounting/creditnotes`).
- Nearest to a sales receipt: BankTransactions `RECEIVE` (refund `SPEND`) on a BANK account (S, D `api/accounting/banktransactions`).
- On the shop's "Early" Xero plan only 20 approved sales invoices a month pass; more answer 400 (S, invoices page).

## How are contacts and items matched?
- Contact Name up to 255 characters and "may no longer be a unique field": match on ContactID; ContactNumber up to 50 (S, D `api/accounting/contacts`).
- Item Code is required, up to 30 characters; Name up to 50; at most 500 a request. An item is tracked stock when `InventoryAssetAccountCode` and `PurchaseDetails.COGSAccountCode` are set (S, D `api/accounting/items`).

## How is tax given?
- `LineAmountTypes` Exclusive, Inclusive or NoTax; invoices default to Exclusive, bank transactions to Inclusive (S, D `api/accounting/types`). A line's `TaxAmount` may be overridden but Xero discourages it; tax is rounded per line (S, D `guides/how-to-guides/rounding-in-xero`).
- `PUT /TaxRates` makes a rate (`ReportTaxType` needed in AU, NZ, UK) (S, D `api/accounting/taxrates`); in AU, NZ and UK a rate made through the API does not count for tax filing (S, D `best-practices/data-integrity/taxes`).
- US: an invoice with automatic sales tax is read-only, and `USSALESTAX` and `AVALARA` cannot be used through the API (S, types page).

## How is currency given?
- `CurrencyCode` and `CurrencyRate` on the invoice (S, D `best-practices/data-integrity/multicurrency`). `PUT /Currencies` adds one that cannot be removed; check `UseMulticurrency` first (S, D `api/accounting/currencies`). Multicurrency needs a higher Xero plan (Xero's own feature page).

## What stops a sale going twice?
- `Idempotency-Key` on POST, PUT, PATCH: kept 6 minutes, up to 128 characters (S, D `guides/idempotent-requests/idempotency`). `InvoiceNumber` is unique for ACCREC, made by Xero when left out; `Reference` up to 255; `summarizeErrors=false` gives a status per record (S, invoices page).
- Webhooks for contacts, invoices, credit notes, signed with `x-xero-signature` (S, D `guides/webhooks/overview`).

## How is a sale undone?
- A DRAFT or SUBMITTED invoice is DELETED; an AUTHORISED one is VOIDED, shown for one with no payments; a payment is reversed with `POST /Payments/{id}` Status DELETED (S, invoices and payments pages).

## Which Xero pages were not read?
- Client-rendered, title only (search excerpts used): D `guides/oauth2/auth-flow`, `scopes`, `custom-connections`, `limits`, and every D `api/accounting/*` and `guides/*` page marked (S).
- Not read: `https://developer.xero.com/page-data/documentation/guides/oauth2/auth-flow/page-data.json` (404); `https://developer.xero.com/llms.txt` (404); the Xero-OpenAPI `xero_accounting.yaml` on GitHub (the first 100,000 of 933,000 characters only).
