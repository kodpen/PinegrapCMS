---
name: erp-parasut-devhub-staging
description: Paraşüt's test environment (staging) for a Developer Portal client: the config_text row, the sign-in and API hosts by token, the read-only Environment select, the amber pill, the screen link
module: parasut
---

## Where is the environment kept?
- The site's, in the `config_text` row `parasut_environment`: `staging`, or no row for live (`parasut_environment()`; any other value reads as live). No table, no column.

## Which addresses does it change?
- Sign-in: the provider (`includes/connect/providers/parasut.php`) takes its authorize and token addresses and the issuer it checks from `parasut_sign_in()`: `id-staging.parasut.com` for staging, `id.parasut.com` for live, the same paths.
- API: `parasut_api_base()` answers `https://api-staging.parasut.com` for staging, `https://api.parasut.com` for live. Found on dev on 2026-11-26 with a staging client (company 35477): `api.parasut.com` answered 401 "Invalid token (DecodeError)" to a staging token, `api-staging.parasut.com` answered 200 (#11957).
- The token in use decides the API host, not the row alone: `parasut_api_environment(parasut_token_choice(), parasut_environment())` is staging only for the portal connection's token in staging. `_parasut_v4_base()` feeds `_parasut_prefix()` (legacy calls and the e-document driver) and the `/v4/me` read in `parasut_me()`; the provider's `check_url` reads `parasut_api_base()` too.
- `init.php` defines `PARASUT_API_BASE` as the live host and `_parasut_base()` prefers it: before #11957 every v4 request went there, so a staging connect failed its `/me` read and kept the older company number.
- The older way (a client from before the portal) has no staging: its `/oauth/token` and `/oauth/authorize` stay on `_parasut_base()` (live), and its token's v4 requests stay live even with the staging row.
- Paraşüt's own screen (`erp_edoc_parasut_panel_address()`): `uygulama-staging.parasut.com` for staging, `uygulama.parasut.com` for live, by the same API environment.

## How is it chosen?
- Paraşüt's page of Connections, "Connection details", the portal client's boxes: the select "Environment" (Live, Test (staging)) after "Redirect address", with the help about a test client; saved with "Save" (`parasut_environment_save()` in the `save_app` branch).
- While a portal connection row is there, in any state, the select is disabled with "Disconnect to change the environment." and the save writes nothing (`parasut_environment_save_value()` answers null): a token of one environment is never used against the other.

## Where does the test mark show?
- `parasut_environment_mark()`: the amber pill "Test environment" only while connected (status active) in staging. On Paraşüt's page it stands before the status line; the e-document screens (ERP dashboard, invoice, settings card) read it through `erp_edoc_parasut_environment()`.

## What is not tested?
- A full round on staging (order buttons, e-document send) after #11957, and whether `uygulama-staging.parasut.com` opens the invoice: the architect's test round on dev.

Tests: `tests/parasut_devhub_staging_test.php`. Related: `erp-parasut-devhub-connection.md`, `erp-parasut-devhub-application.md`.
