---
name: quickbooks-service-facts
description: What QuickBooks Online asks of the accounting sync (2026-11): sign-in, realmId, tokens, App Assessment, partner fees, limits, sale records, tax, requestid
---

## Where do these facts come from?
- S = `https://static.developer.intuit.com/output_html/qbo/docs/`: the `developer.intuit.com/app/developer/qbo/docs/` pages come back as empty shells, and the entity reference (`salesreceipt` 403) was read only through search excerpts. Nothing was tried against a real sandbox.

## How does QuickBooks Online sign in?
- OAuth 2.0 code flow: authorize `https://appcenter.intuit.com/connect/oauth2`, token `https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer`, revoke `https://developer.api.intuit.com/v2/oauth2/tokens/revoke`, scope `com.intuit.quickbooks.accounting`; Intuit says to read them from the discovery document (`https://developer.api.intuit.com/.well-known/openid_configuration`, sandbox `openid_sandbox_configuration`).
- The company is only the `realmId` parameter on the return address. Calls go to `https://quickbooks.api.intuit.com/v3/company/<realmId>/<entity>` (sandbox `sandbox-quickbooks.api.intuit.com`); minor versions below 75 are taken as 75.
- Access token one hour; refresh token 100 days, rolling (after 24 h a renewal returns a new one and the old stops at once), five years at most.

## What does registering an app ask?
- An Intuit developer account and an app. Development keys reach only sandbox companies; production keys come after the App Assessment Questionnaire, with EULA and privacy addresses, host domain, launch, connect and disconnect addresses. Return addresses are registered apart per environment, HTTPS, localhost only in sandbox.

## What does the App Partner Program cost?
- From 2025-07-28 (US) and 2025-11-03 (UK, AU, CA) every app is in the free Builder tier: Core calls (mainly writes) free and not counted; CorePlus calls (reads, queries, reports) up to 500,000 a month, then blocked. Silver 300 USD a month.

## What are the request limits?
- 500 requests a minute per company, 10 a second per company and app; batch 40 a minute; 120 s timeout; 1,000 entities a query. Over the limit 429; wait 60 s.

## What does a sale take?
- Paid at once: SalesReceipt; paid later: Invoice then Payment (`Line.LinkedTxn`). Refund: RefundReceipt (`DepositToAccountRef`) or CreditMemo. Create and update are POST; delete adds `?operation=delete`.
- Customers and items exist before the sale. Customer `DisplayName` unique across customers, vendors and employees (repeat: 6240). Item `Name` unique, up to 100; inventory tracking cannot be turned back.
- `DocNumber` up to 21, no guard against a repeat (6140). An update carries `SyncToken` (stale: 5010).

## How are tax and currency given?
- US (Automated Sales Tax): `TaxCodeRef` TAX or NON; `TxnTaxDetail.TotalTax` is kept; `GlobalTaxCalculation` refused; at most 750 lines.
- Elsewhere: `TaxCodeRef` per line, `GlobalTaxCalculation` TaxExcluded or TaxInclusive; sales tax is turned on only in QuickBooks' screens.
- `CurrencyRef` and `ExchangeRate`; multicurrency is off by default and cannot be turned off.

## What stops a second sale, and how is one undone?
- `requestid` up to 50 characters, unique per company: a repeat returns the first answer.
- A transaction is deleted for good; lists only go inactive. Void is documented for Payment; for SalesReceipt and Invoice only in forum posts.
