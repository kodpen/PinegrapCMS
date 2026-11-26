---
name: parasut-devhub-staging
description: Plan (owner 2026-11-26): Paraşüt's test environment (staging) for a Developer Portal client: the choice on Paraşüt's page of Connections, the sign-in host, the mark on the status line; step PS1
priority: 6
---

## What is asked?
- Owner (2026-11-26, in the architect's window): the staging client Paraşüt gave with the partner application (`erp/erp-parasut-devhub-application.md`) is to connect on dev, so the integration is tried while the application is reviewed. Today the provider signs in at `id.parasut.com` only (`erp/erp-parasut-devhub-connection.md`).

## What is decided?
- Architect (2026-11-26): the environment is the site's, kept in one `config_text` row, `parasut_environment` (`staging`, or no row for live). No table and no column.
- The provider (`includes/connect/providers/parasut.php`) takes its authorize and token addresses from it: `id-staging.parasut.com` for staging, the same paths. Everything else stays the framework's.
- The API address is read through one function (`parasut_api_base()` or the place that builds it today), which answers `https://api.parasut.com/v4` for both until the first connect on dev shows otherwise: Paraşüt's pages name no staging API address. That connect is made by the architect with the owner's client; what it shows is corrected in one line and written into `erp/erp-parasut-devhub-client.md`.
- The older way (a client from before the portal) has no staging: the choice belongs to the portal client only.

## How is the screen put together?
- Paraşüt's page of Connections, "Connection details", in the application boxes drawn by `connections_app_form()`, after "Redirect address": a select "Environment" with "Live" and "Test (staging)", and under it the help "A test client from Paraşüt's Developer Portal works only with the test environment. Documents made there are not real."
- The choice is saved with "Save", as the client is. While the site is connected the select is read-only with the line "Disconnect to change the environment.": a token of one environment is never used against the other.
- Connected in the test environment, the status line carries an amber pill "Test environment" before the company's name, and the e-document screens that send through Paraşüt show the same pill beside the provider's name, so a test document is never taken for a real one.
- Every text through `lang()` with `tr_add.php`; the look from the Design examples screen.

## Steps
- PS1 (S, done #11819): the row, the provider's hosts, the select and its read-only state, the pill. Tests, red first: the authorize and token addresses for each environment, the select's two states, the pill only when connected in staging, the older way untouched. What no test reaches is said in the PR: a connect against Paraşüt's staging.
- Claim as `[plan:parasut-devhub-staging] PS1 — test environment`. When PS1 has landed and dev is connected with the staging client, the facts go into `erp/erp-parasut-devhub-connection.md` and `erp-parasut-devhub-client.md` and this plan is deleted.
