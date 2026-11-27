---
name: accounting-sync-census-xero
description: QuickBooks and Xero sync census: Xero's OAuth sign-in and tenant, tokens, granular scopes, Custom Connections closing, pricing tiers, limits, registering an app
---

## Where were Xero's pages read?
- D = `https://developer.xero.com/`. Read directly: the FAQ, pricing, custom-development and changelog pages, the OpenAPI files `xero_accounting.yaml` and `xero-identity.yaml` (v20.1.0) whole, the discovery document `https://identity.xero.com/.well-known/openid-configuration`. (S) = search excerpts of the page named.

## How does Xero sign in?
- OAuth 2.0 code flow: authorize `https://login.xero.com/identity/connect/authorize`, token `https://identity.xero.com/connect/token` (client_secret_basic or _post), revoke `https://identity.xero.com/connect/revocation`, PKCE S256 (discovery document). Revoking the refresh token removes all the app's connections (SDK README).
- The company: `GET https://api.xero.com/connections` (filter `authEventId`) lists the organisations: `id`, `tenantId`, `authEventId`, `tenantType` ORGANISATION or PRACTICE, `tenantName`, `createdDateUtc`, `updatedDateUtc`; `DELETE /connections/{id}` answers 204 (identity yaml). The access token carries `authentication_event_id` (SDK README). Every accounting path requires the `xero-tenant-id` header (accounting yaml).
- A PKCE app type without a secret is for native apps; single-page apps are not supported (D `faq/oauth2`).

## How long do tokens live?
- Access token 30 minutes (`expires_in` 1800); refresh token 60 days unused, a new one on each renewal, the old one usable 30 minutes if no answer came (D `faq/oauth2`). `offline_access` asks the refresh token (SDK examples; not in the OpenAPI). Return addresses: absolute, no wildcards, at most 50 (same FAQ), https but localhost (S).

## Which scopes?
- Apps made on or after 2026-03-02 use granular scopes; older apps keep the broad ones until 2027-09-13 (D `faq/granular-scopes`, D `changelog`). The OpenAPI v20.1.0 still names only the broad ones.
- The changelog splits `accounting.transactions` into `accounting.invoices` (invoices, credit notes), `accounting.payments`, `accounting.banktransactions`, `accounting.manualjournals`; `accounting.settings` (accounts, tax rates, items) and `accounting.contacts` are unchanged. `journals.read` needs the Advanced tier from 2026-03-04.
- From 2026-06-29 only the journal endpoints (a Standard user's reporting permission) and a contact's bank details (BankAccountAdmin) check the authorising person's Xero permission (D `changelog`).

## What does registering an application ask of the owner?
- A free Xero account; the app is registered at `D app/manage` for a client id and secret (D `faq/getting-started`). The form asks the type (Web app, PKCE, Custom connection), an app address (https) and a return address (S, postman tool page). A demo company comes with the account and resets after 28 days (S, development-accounts page).
- Custom Connections (client credentials, one organisation, AU, NZ, UK and US only, AUD 10, NZD 10, GBP 5 or USD 5 a month: D `custom-development`) take no new app or subscription from 2026-12-01 (D `faq/custom-integration`).

## What does Xero charge the app?
- Since 2026-03-02, AUD a month, data in unlimited (D `pricing`, D `faq/pricing-and-policy-updates`): Starter 5 connections, free; Core 50, 35; Plus 1,000, 245, certification; Advanced 10,000, 1,445, certification and a security assessment; Enterprise on application.
- One site with its own app and one organisation fits Starter. An organisation connects at most 5 uncertified apps (S, managing-connections page).

## What are the request limits?
- Per organisation: 5 at once, 60 a minute, 5,000 a day; app-wide 10,000 a minute; headers `X-DayLimit-Remaining`, `X-MinLimit-Remaining`, `X-AppMinLimit-Remaining`; over it, 429 with `Retry-After`; 3.5 MB a request, about 50 records a call (D `faq/limits`). A list answers up to 100 records a page (accounting yaml).
- The newer pricing page gives Starter 1,000 calls a day per organisation, not the FAQ's 5,000.
