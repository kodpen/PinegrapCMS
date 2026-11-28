---
name: xero-service-facts
description: What Xero asks of the accounting sync (2026-11): sign-in, tokens, scopes, app tiers, Custom Connections closing, limits, sale records, Idempotency-Key
---

## Where do these facts come from?
- D = `https://developer.xero.com/`; the accounting and identity OpenAPI files v20.1.0 read whole; the discovery document `https://identity.xero.com/.well-known/openid-configuration`. Many D pages render only in a browser and were read through search excerpts. Nothing was tried against a real organisation.

## How does Xero sign in?
- OAuth 2.0 code flow with PKCE S256: authorize `https://login.xero.com/identity/connect/authorize`, token `https://identity.xero.com/connect/token`, revoke `https://identity.xero.com/connect/revocation`. Revoking the refresh token removes all the app's connections.
- The organisation: `GET https://api.xero.com/connections` (filter `authEventId`, the token's `authentication_event_id`); `tenantType` ORGANISATION or PRACTICE. Every accounting call sends `xero-tenant-id`.
- Access token 30 minutes; refresh token 60 days unused, a new one on each renewal, the old one usable 30 minutes; `offline_access` asks it. Return addresses absolute, no wildcards, at most 50.

## Which scopes?
- Apps made on or after 2026-03-02 use granular scopes (`accounting.invoices`, `accounting.payments`, `accounting.banktransactions`, `accounting.manualjournals`; `accounting.settings` and `accounting.contacts` unchanged); older apps keep the broad ones until 2027-09-13.

## What does an app cost the site's owner?
- A free Xero account and a Web app registered at D `app/manage`; a demo company resets after 28 days.
- App tiers since 2026-03-02, AUD a month: Starter 5 connections, free; Core 50, 35; Plus 1,000, 245; Advanced 10,000, 1,445; Enterprise on application. One site with one organisation fits Starter; an organisation connects at most 5 uncertified apps.
- Custom Connections (client credentials) take no new app from 2026-12-01.
- The shop's own "Early" Xero plan passes only 20 approved sales invoices a month; more answer 400. Multicurrency needs a higher Xero plan.

## What are the request limits?
- Per organisation: 5 at once, 60 a minute, 5,000 a day (the newer pricing page gives Starter 1,000 a day); app-wide 10,000 a minute. Headers `X-DayLimit-Remaining`, `X-MinLimit-Remaining`, `X-AppMinLimit-Remaining`; over it 429 with `Retry-After`. 3.5 MB a request, about 50 records a call, 100 a list page.

## What does a sale take?
- Invoice Type `ACCREC`, a Contact, lines with `AccountCode`/`AccountID`, `TaxType`, `LineAmountTypes` (Exclusive by default). Payment to a BANK account or one with `EnablePaymentsToAccount`, no more than owed. Refund: `ACCRECCREDIT` credit note, allocated or paid out.
- Contact Name may not be unique: match on ContactID; `ContactNumber` up to 50, meant for external ids. Item `Code` up to 30, `Name` up to 50.
- In AU, NZ and UK a tax rate made through the API does not count for filing; a US automatic sales tax invoice is read-only.
- `CurrencyCode` and `CurrencyRate` (without a rate, the day rate); a currency added cannot be removed.

## What stops a second sale, and how is one undone?
- `Idempotency-Key` up to 128 characters, kept 6 minutes. `InvoiceNumber` unique for ACCREC.
- An AUTHORISED invoice with no payments is VOIDED; a payment is reversed with `POST /Payments/{id}` Status DELETED.
