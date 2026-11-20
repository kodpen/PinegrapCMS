---
name: connections-provider-page
description: Google's and Microsoft's own pages on Connections (CX10a, #10512): application, accounts, both redirect addresses, Google's APIs to turn on, the "API not enabled" error, the verification warning
module: google_cloud
---

## Which providers have a page of their own?
- `connections.php?service=google` and `…=microsoft` (CX10a, #10512). No registry service carries these keys, so `pg_connections_screen_page_providers()` maps page key => provider and `pg_connections_screen_provider_page()` answers the provider when the key is no service and the provider's row is on the list. `pg_connections_count()` stays 78.
- Their Account providers rows open the page; Dropbox keeps its drawer. The old addresses `connections.php#connections-google` / `#connections-microsoft` are sent to the page by the list's script (`$moved_drawers`).

## What does the page show?
- "Status" ("Durum"): the state, the application's pill, the site's own accounts (`connections_account_items()`, shared with the drawer: Check, Connect again, Disconnect) and "Connect a Google account" (quiet).
- "Connection details" ("Bağlantı bilgileri", manager, the screen's gate): `connections_app_form()` with the client, secret, options (Microsoft's tenant) and Google's "Use the Sign in with Google application"; writes return to the page.
- In it, both redirect addresses (`pg_google_cloud_redirect_addresses()`: Sign in's and `connect.php`'s) each with Copy and the "both must be added" note, on both pages. The drawers still show the one connect address.
- "Where it is used": `pg_connections_screen_provider_uses()`, each registry service the provider serves by its accounts or as sign-in (`covers.connect`), linking to its page.

## What does Google's page add?
- "APIs to turn on in the project" ("Projede açılması gereken API'ler"): `pg_google_cloud_apis()` reads the registry's `apis` (service name => product name) of the Google features, each with the features using it, "In use" when one is connected, and "Turn on" to `console.cloud.google.com/apis/library/<api>`.
- "Google's verification warning": `pg_google_cloud_verification_notes()`. The Gmail mailbox form reads the same function with `'gmail'` (restricted scope, security assessment), so its words did not change.

## How is "API not enabled" shown?
- `pg_google_cloud_api_disabled()` reads Google's "… API has not been used in project … or it is disabled" answer; `connections_error_html()` shows "Google Cloud projenizde <API> açık değil" with "Açın" (the enable page in that project) and the raw text under Details. Used for an account's last error, a service page's last error and Check's answer.
- Not yet elsewhere: the Workspace calendar's own error line still shows Google's text.

## Which Google pages back the warning (2026-11-20)?
- support.google.com/cloud/answer/15549945: Testing limits use to test users, who see a warning before they grant the scopes; their authorisation ends seven days after consent; Internal limits it to the organisation; In production completes verification.
- developers.google.com/identity/protocols/oauth2/production-readiness/sensitive-scope-verification: reading Google Calendar events is a sensitive scope; Internal and Testing need no verification.

## What is left for CX10b?
- The Sign in with Google application (`config.oauth_google_*`) is still typed in Accounts and sign-in; CX10b moves it onto Google's page under that card's gate. Microsoft's sign-in switch already points to `connections.php?service=microsoft`.

Tests: `tests/google_cloud_test.php`, `tests/connections_layout_test.php`, `tests/workspace_mail_google_steps_test.php`.
