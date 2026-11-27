---
name: accounting-sync-census-quickbooks-sale
description: QuickBooks and Xero sync census: what QuickBooks Online takes for a sale (sales receipt, invoice, payment, refund), tax codes, currency, duplicate guards, pages not read
---

## Where do these facts come from?
- S = `https://static.developer.intuit.com/output_html/qbo/docs/`. R = `https://developer.intuit.com/app/developer/qbo/docs/api/accounting/all-entities/`, the entity reference, which did not load; R facts are from search excerpts of it.

## What does QuickBooks Online take for a sale?
- Create and update are POST to the entity; delete adds `?operation=delete` (S `learn/rest-api-features.html`).
- A sale paid at once is a SalesReceipt; a sale paid later is an Invoice, then a Payment (S `learn/learn-basic-bookkeeping/invoicing.html`).
- Invoice: `CustomerRef` and `Line` (SalesItemLineDetail with `ItemRef`, and `Amount`) (S `workflows/create-an-invoice.html`). Payment: `TotalAmt` and `CustomerRef`, tied to the invoice through `Line.LinkedTxn` (R `payment`). RefundReceipt: a line and `DepositToAccountRef`, a bank or current asset account (R `refundreceipt`). CreditMemo's required fields were not confirmed.
- Customers, items and accounts must exist before the sale (S `develop/basic-implementations/basic-invoicing-implementation.html`). Customer `DisplayName` is unique across customers, vendors and employees, up to 500 characters (R `vendor`); a repeat is error 6240 (S `develop/troubleshooting/error-codes.html`).
- Item `Name` is unique (S `workflows/manage-inventory.html`), up to 100 characters; an inventory item needs `QtyOnHand`, `InvStartDate`, `AssetAccountRef` and `TrackQtyOnHand`, which cannot be turned back (R `item`). Inventory is in the Plus and Advanced plans (Intuit's help pages).

## How is tax given?
- US companies (Automated Sales Tax): `TaxCodeRef` TAX or NON per line; QuickBooks computes the tax from the ship-to address, and `TxnTaxDetail.TotalTax` sent with `TxnTaxCodeRef` is kept; `GlobalTaxCalculation` is refused; at most 750 lines on a taxed sale; codes made through the API are replaced (S `workflows/calculate-sales-tax/automated-sales-tax-for-us-locales.html`).
- Other countries: `TaxCodeRef` per line, `GlobalTaxCalculation` TaxExcluded or TaxInclusive; an override names every `TaxRateRef` of the code; codes and rates are made through TaxService; sales tax is turned on only in QuickBooks' own screens (S `…/automated-sales-tax-for-non-us-locales.html`).

## How is currency given?
- `CurrencyRef` and `ExchangeRate` (home units per foreign unit). Multicurrency is off by default, is turned on only in QuickBooks' screens and cannot be turned off; home and customer currency never change (S `workflows/manage-multiple-currencies.html`).

## What stops a sale going twice?
- `requestid`: up to 50 characters (36 in a batch), unique per company; a repeat returns the first answer instead of a second write (S `learn/learn-basic-field-definitions.html`).
- `DocNumber` (up to 21 characters) is no guard; a repeated number is error 6140 (same page).
- An update carries `SyncToken`; a stale one is error 5010 (R `payment`). Webhooks (S `develop/webhooks.html`) and change data capture, 30 days back (S `learn/explore-the-quickbooks-online-api/change-data-capture.html`).

## How is a sale undone?
- A transaction is deleted for good; customers and lists only go inactive (S `learn/explore-the-quickbooks-online-api.html`). Void is documented for Payment (R `payment`); for SalesReceipt and Invoice only in forum posts.

## Which Intuit pages were not read?
- `developer.intuit.com/app/developer/qbo/docs/*` and `developer.intuit.com/partners/benefits`: empty shells. `developer.intuit.com/quickbooks-online/docs/*`: 404. `developer.intuit.com/llms-full.txt`: over 10 MB.
- S `api/accounting/all-entities/salesreceipt.html`: 403. `help.developer.intuit.com/s/article/QuickBooks-Online-API-Best-Practices`: needs JavaScript. `medium.com/intuitdev/important-changes-to-refresh-token-policy-8443779d40db`: 403.
