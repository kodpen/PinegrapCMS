---
name: xero-connection
description: Xero connected (#12069): the site's own app, provider and purpose, the organisation read from /connections, the three accounts, the sealed service entry, when Xero is called, Disconnect
module: xero, xero_page
---

## How is Xero connected?
- Each site registers its own free Web app on Xero's developer portal and enters its Client id and secret under Connection details › Application on `connections.php?service=xero`; Connect sits on the Status card.
- Provider `includes/connect/providers/xero.php`: `identity => false` (one connection, owner user 0), `client_auth` basic, `listed => false` (no row in the provider list), `offline_access` for a refresh token, a 30-minute access token.
- Purpose `includes/connect/purposes/xero.php`, owner `site`, granular scopes `accounting.settings`, `accounting.contacts`, `accounting.invoices`, `accounting.payments` (apps made since 2026-03-02 cannot ask the old broad scopes).
- Registry row `xero` in the "E-documents and tax" group beside Paraşüt, state kind `account`.

## Who sees what?
- The page is manager-only like every Connections page; Connection details and its two forms need Site Settings › Accounting (`connections_accounting_may_manage()`, can-key `xero`).

## Where is the state kept?
- One sealed service entry `pg_connect_service_get/put('xero')`, shape `xero_state_parse()`: `connection_id`, `tenant_id`, `tenant_name`, `tenants` (id => name, up to 50), `accounts` (`income`, `clearing`, `bank`, each id, code, name), `connected_at`. No table or column.
- Ids are lower-case UUIDs. Accounts are dropped while no organisation is chosen; choosing another organisation empties them.

## Which organisation is used?
- None while QuickBooks keeps the books (a company connected): the return says to disconnect QuickBooks first (`quickbooks-connection.md`).
- After the return, `xero_after_connect()` lists `GET https://api.xero.com/connections`, keeps ORGANISATION tenants only and picks: the one chosen before if still listed, else the single one allowed in this sign-in (`authEventId` equal to the token's `authentication_event_id`), else the only one. Otherwise the page asks under Connection details.
- Every accounting call sends `xero-tenant-id`.

## Which accounts fit each role?
- Only ACTIVE accounts of `GET /api.xro/2.0/Accounts?order=Name`. Income: class or type REVENUE, or type SALES or OTHERINCOME. Clearing (online payments until the payout): type BANK or `EnablePaymentsToAccount`. Bank (transfer, cash, on delivery): type BANK.
- Save rereads the chart and refuses an id that is not offered for its role; a kept account no longer offered is shown with a note.

## When is Xero called?
- Only on the connect return (token, /connections), when the page is drawn with an organisation chosen (the chart of accounts and the tax rates, `xero-tax-rates.md`) and when the accounts or the tax rates are saved. The general job sends the queued sales, payments and refunds (`accounting-sync-queue.md`, `xero-sync-driver.md`).

## What does Disconnect do?
- Revokes the token at Xero (`revoke_client_auth`), deletes the connection and the `xero` service entry (`xero_forget()`).

## What was not tried live?
- No call was made to a real Xero organisation; the shapes come from Xero's OpenAPI files (`plans/accounting-sync-census-xero.md`).
