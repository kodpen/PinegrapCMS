---
name: accounting-sync-census-xero
description: QuickBooks and Xero sync census: Xero's OAuth sign-in and tenant, tokens, granular scopes, Custom Connections closing, pricing tiers, limits, registering an app
---

## Where were Xero's pages read?
- D = `https://developer.xero.com/`. The FAQ, pricing, custom-development and changelog pages were read directly. The `D documentation/...` pages render only in a browser; facts marked (S) come from search excerpts of the page named. List: end of `accounting-sync-census-xero-sale.md`.

## How does Xero sign in?
- OAuth 2.0 code flow: authorize `https://login.xero.com/identity/connect/authorize`, token `https://identity.xero.com/connect/token` with Basic client authentication, revoke `POST https://identity.xero.com/connect/revocation` (S, D `documentation/guides/oauth2/auth-flow`). The framework has `client_auth` basic (`includes/fn/connect.php` 1296).
- The company: `GET https://api.xero.com/connections` after the token lists the organisations; every call carries `xero-tenant-id`; `DELETE /connections/{connectionId}` drops one (S, same page). This fits an after-connect step like Paraşüt's (`accounting-sync-census-parasut.md`).
- A PKCE app type without a secret exists (S, D `documentation/guides/oauth2/pkce-flow`).

## How long do tokens live?
- Access token 30 minutes; refresh token 60 days unused, a new one on each renewal, the old one usable 30 minutes if no answer came; `offline_access` is needed (D `faq/oauth2`). Return addresses: absolute, no wildcards, at most 50 (D `faq/oauth2`), https but localhost (S).

## Which scopes?
- Apps made on or after 2026-03-02 use granular scopes; older apps until 2027-09-13 (D `faq/granular-scopes`, D `changelog`). For a sale: `accounting.invoices` (invoices, credit notes), `accounting.payments`, `accounting.contacts`, `accounting.settings` (accounts, tax rates, items), and `accounting.banktransactions` for bank transactions (S, D `documentation/guides/oauth2/scopes`).
- From 2026-06-29 a call fails when the person who authorised the connection lacks that permission in Xero (D `changelog`).

## What does registering an application ask of the owner?
- A free Xero account; the app is registered at `D app/manage` for a client id and secret (D `faq/getting-started`). The form asks the type (Web app, PKCE, Custom connection), a company or app address (https) and a return address (S, D `documentation/sdks-and-tools/tools/postman`). A demo company comes with the account and resets after 28 days (S, D `documentation/development-accounts`).
- Custom Connections (client credentials, one organisation, AU, NZ, UK and US only, AUD 10, NZD 10, GBP 5 or USD 5 a month: D `custom-development`) take no new app or subscription from 2026-12-01 (D `faq/custom-integration`).

## What does Xero charge the app?
- Since 2026-03-02, AUD a month, data in unlimited (D `pricing`, D `faq/pricing-and-policy-updates`): Starter 5 connections, free; Core 50, 35; Plus 1,000, 245, certification; Advanced 10,000, 1,445, certification and a security assessment; Enterprise on application.
- One site with its own app and one organisation fits Starter. An organisation connects at most 5 uncertified apps (S, D `documentation/best-practices/managing-connections/connections`).

## What are the request limits?
- Per organisation: 5 at once, 60 a minute, 5,000 a day; app-wide 10,000 a minute; headers `X-DayLimit-Remaining`, `X-MinLimit-Remaining`, `X-AppMinLimit-Remaining`; over it, 429 with `Retry-After`; 3.5 MB a request, about 50 records a call (D `faq/limits`).
- The newer pricing page gives Starter 1,000 calls a day per organisation, against the FAQ's flat 5,000.
