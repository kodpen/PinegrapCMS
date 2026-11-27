---
name: quickbooks-connection
description: QuickBooks Online connected (AS6): the site's own Intuit app, environment, discovery addresses, realmId kept from the return, the company read, accounts and tax codes chosen, one service at a time
module: quickbooks, quickbooks_page
---

## How is QuickBooks connected?
- Each site makes its own app on Intuit's developer portal and enters its Client ID and secret under Connection details › Application on `connections.php?service=quickbooks`; Connect sits on the Status card. The setup lines say development keys reach only sandbox companies and production keys come after Intuit's App Assessment Questionnaire.
- Provider `includes/connect/providers/quickbooks.php`: `identity => false` (one connection, owner user 0), `client_auth` basic, no identity scope, scope `com.intuit.quickbooks.accounting` (purpose `quickbooks`, owner `site`), `listed => false`, access token an hour.
- Registry row `quickbooks` (group "E-documents and tax", state kind `account`, origin US).

## How is the company named?
- Only by `realmId` on the return address. A provider lists the return parameters to keep as `return_params` (name => pattern); `pg_connect_return_params()` keeps the matching ones, `pg_connect_finish()` answers them as `params`, `connect.php` puts them in the flash, and Connections hands `realmId` to `quickbooks_after_connect()`, which reads `companyinfo/<realmId>` for the name and country.
- Another company leaves no account chosen. Connecting again with QuickBooks' own company choice is how the company changes.

## Which environment and addresses?
- "Environment" under Connection details: Production or Sandbox (development keys), saved before connecting, read-only while connected. Calls go to `quickbooks.api.intuit.com` or `sandbox-quickbooks.api.intuit.com` `/v3/company/<realmId>/…?minorversion=75`.
- Sign-in addresses come from Intuit's discovery document of the environment, read while the page is drawn at most once a day (`quickbooks_discovery_refresh()`); only HTTPS addresses on intuit.com are taken, else the documented ones stand (`quickbooks_sign_in()`).

## Where is the state kept?
- One sealed service entry `quickbooks` (`quickbooks_state_parse()`): environment, sign_in, sign_in_read_at, connection_id, realm_id, company_name, country, accounts (income, clearing, bank: id, number, name), connected_at. No table or column. Disconnect revokes the token and keeps only the environment and addresses (`quickbooks_forget()`).

## Which accounts and tax codes?
- Accounts from `select * from Account where Active = true`: income Income or Other Income; clearing Bank or Other Current Asset (Undeposited Funds); bank Bank. Save rereads and refuses one not offered.
- Tax codes from `select * from TaxCode where Active = true`, offered when they have a sales rate; each site rate matched once, kept as an `accounting_sync` tax pairing keyed by the realm, as Xero's (`xero-tax-rates.md`). A US company matches nothing: TAX or NON and the site's total (AS7's driver).

## One accounting service at a time?
- `pg_accounting_sync_target()` is Xero with an organisation chosen, else QuickBooks with a company. `quickbooks_after_connect()` keeps no company while Xero keeps the books, and `xero_after_connect()` picks no organisation while QuickBooks does; each page says to disconnect the other first.
- The Events card shows only on the page of the service the site books into. No QuickBooks driver yet, so its rows wait.

## What was not tried live?
- No call to a real Intuit sandbox or company (no test keys); shapes come from Intuit's pages (`plans/accounting-sync-census-quickbooks.md`). Not verified: Intuit ignoring the PKCE parameters, and its revocation taking a form body. Tests: `tests/quickbooks_test.php`.
