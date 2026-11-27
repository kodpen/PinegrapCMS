---
name: accounting-sync-census-xero-sale
description: QuickBooks and Xero sync census: what Xero takes for a sale (invoice, payment, credit note, contact, item), tax types, currency, Idempotency-Key, pages not read
---

## What does Xero take for a sale?
- D = `https://developer.xero.com/documentation/`; base `https://api.xero.com/api.xro/2.0`; Y = the accounting OpenAPI `xero_accounting.yaml` v20.1.0; (S) = from search excerpts of the page named.
- Invoice: `PUT` or `POST /Invoices`, Type `ACCREC`, a Contact and lines with `ItemCode`, `AccountCode` (or `AccountID`), `TaxType` (Y). Lines are tax-exclusive unless `LineAmountTypes` says otherwise (Y). `Url` is shown as "Go to [appName]" (Y). Line discounts (`DiscountRate`, `DiscountAmount`) work on ACCREC only, never on credit notes (Y). No line cap is stated.
- `SubTotal`, `TotalTax`, `Total` are written only with an opt-in, `RoundingAmount` up to 0.10 either way; `unitdp=4` opts in to four decimals (Y).
- A paid web order: the invoice as AUTHORISED, then `PUT /Payments` (several) or `POST /Payments` (one) with the invoice, an account by `AccountID` or `Code` (type BANK or `EnablePaymentsToAccount`), a date and an amount no higher than what is owed (Y). Xero advises a clearing account per payment type (S, payment-processor how-to page).
- Refund: a credit note (Type `ACCRECCREDIT`), AUTHORISED, then allocated with `PUT /CreditNotes/{id}/Allocations` (not in the same call), or paid out through Payments with its `CreditNote` (Y).
- Nearest to a sales receipt: BankTransactions `RECEIVE` (refund `SPEND`) on a BANK account (Y).
- On the shop's "Early" Xero plan only 20 approved sales invoices a month pass; more answer 400 (S, invoices page).

## How are contacts and items matched?
- Contact `Name` and `EmailAddress` up to 255 characters; Name "may no longer be a unique field" (S): match on ContactID. `ContactNumber` up to 50, written only through the API and meant to identify contacts in external systems (Y).
- Item `Code` required, up to 30 characters; `Name` up to 50; an item is tracked stock when `InventoryAssetAccountCode` and `PurchaseDetails.COGSAccountCode` are set (Y). At most 500 a request (S, D `api/accounting/items`).

## How is tax given?
- `LineAmountTypes` Exclusive, Inclusive or NoTax; bank transactions default to Inclusive (S, D `api/accounting/types`). A line's `TaxAmount` may be overridden (Y) but Xero discourages it; tax is rounded per line (S, D `guides/how-to-guides/rounding-in-xero`).
- `PUT /TaxRates` makes a rate (`ReportTaxType` needed in AU, NZ, UK) (S, taxrates page); in AU, NZ and UK a rate made through the API does not count for tax filing (S, data-integrity taxes page).
- US: an invoice with automatic sales tax is read-only, and `USSALESTAX` and `AVALARA` cannot be used through the API (S, types page).

## How is currency given?
- `CurrencyCode` and `CurrencyRate` on the invoice; without a rate the XE.com day rate is used (Y). `PUT /Currencies` adds one that cannot be removed; check `UseMulticurrency` first (S, currencies page). `GET /Organisation` gives `BaseCurrency` (Y). Multicurrency needs a higher Xero plan (Xero's own feature page).

## What stops a sale going twice?
- `Idempotency-Key` header on the create and update calls, up to 128 characters (Y); kept 6 minutes (S, D `guides/idempotent-requests/idempotency`). `InvoiceNumber` up to 255, unique for ACCREC, made by Xero when left out; `Reference` for ACCREC only; `summarizeErrors=false` answers 200 with a status per record (Y).
- Webhooks for contacts, invoices, credit notes, signed with `x-xero-signature` (S, D `guides/webhooks/overview`).

## How is a sale undone?
- A DRAFT or SUBMITTED invoice is DELETED; an AUTHORISED one is VOIDED, shown for one with no payments (S, invoices page); a payment is reversed with `POST /Payments/{id}` Status DELETED (Y).

## Which Xero pages were not read?
- Client-rendered, title only (search excerpts used): D `guides/oauth2/auth-flow`, `scopes`, `custom-connections`, `limits`, and every D page marked (S). `llms.txt` and the page-data files answer 404.
