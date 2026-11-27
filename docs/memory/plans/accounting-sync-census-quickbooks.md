---
name: accounting-sync-census-quickbooks
description: QuickBooks and Xero sync census, QuickBooks Online part: its OAuth sign-in, token lifetimes, request limits, the App Partner Program fees and what registering an application asks of the owner
---

## Where were Intuit's pages read?
- S = `https://static.developer.intuit.com/output_html/qbo/docs/`: the `developer.intuit.com/app/developer/qbo/docs/` pages came back as empty shells, and the same text was read at S. Pages not read: end of `accounting-sync-census-quickbooks-sale.md`.

## How does QuickBooks Online sign in?
- OAuth 2.0 code flow: authorize `https://appcenter.intuit.com/connect/oauth2`, token `https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer`, revoke `https://developer.api.intuit.com/v2/oauth2/tokens/revoke`, scope `com.intuit.quickbooks.accounting` (S `develop/authentication-and-authorization/oauth-2.0.html`).
- Intuit says to read these three from the discovery document (`https://developer.api.intuit.com/.well-known/openid_configuration`, sandbox `openid_sandbox_configuration`).
- The company is the `realmId` parameter on the return address. `pg_connect_finish()` (`includes/fn/connect.php` 1748) reads only `state` and `code` there today, so a QuickBooks provider needs the return to keep `realmId`.
- Calls go to `https://quickbooks.api.intuit.com/v3/company/<realmId>/<entity>`, sandbox `https://sandbox-quickbooks.api.intuit.com` (S `get-started/create-a-request.html`). Minor versions 1-74 lost support on 2025-08-01; a lower one or none is taken as 75 (S `learn/explore-the-quickbooks-online-api/minor-versions.html`).

## How long do tokens live?
- Access token one hour. Refresh token 100 days, rolling: after 24 h a renewal returns a new value and the old one stops at once; five years at most (S `develop/authentication-and-authorization/faq.html`). The renewal under a lock (`api/api-connected-accounts-renewal.md`) fits a rotating refresh token.

## What does registering an application ask of the owner?
- An Intuit developer account and an app. Development keys reach only sandbox companies; production keys come after the App Assessment Questionnaire (S `get-started/get-client-id-and-client-secret.html`).
- For production the app gives EULA and privacy addresses, host domain, launch, connect and disconnect addresses, categories, regulated industries and hosting regions (S `go-live/publish-app.html`).
- Return addresses are registered apart for sandbox and production: HTTPS, no IP address, localhost only in sandbox (S `develop/authentication-and-authorization/set-redirect-uri.html`). Each site has its own return address (`pg_connect_redirect_uri()`, `connect.php` 820).
- The launch address may be the app's sign-in page and the disconnect address a static page (S `get-started/app-settings.html`); an Intuit forum answer, not the docs, says placeholder addresses pass for a private app that is not published. The docs read do not say whether such an app is spared the questionnaire.

## What does the App Partner Program cost?
- S `get-started/partner-faq.html`: in force for US apps from 2025-07-28, UK, AU and CA from 2025-11-03. Every app is put in the Builder tier. Core calls (mainly writes: invoices, customers) are free and not counted; CorePlus calls (mainly reads, queries, reports) are metered; sandbox and OAuth calls are not counted.
- Builder: free, 500,000 CorePlus calls a month, then blocked. Silver, as the page's example: 300 USD a month and 3.50 USD per 1,000 calls over its credits. Gold and Platinum fees are not on the page. Premium APIs need Silver or above (S `learn/premium-apis.html`).

## What are the request limits?
- S `learn/limits-and-throttles.html`: 500 requests a minute per company, 10 a second per company and app; batch 40 a minute per company and app and 120 per company, at most 30 operations a batch advised (the older `learn/explore-the-quickbooks-online-api/batch.html` says 10); 120 s timeout; 1,000 entities per query.
- Over the limit the answer is 429; wait 60 s.
