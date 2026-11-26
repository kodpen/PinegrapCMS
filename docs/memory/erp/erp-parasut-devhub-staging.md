---
name: erp-parasut-devhub-staging
description: Paraşüt's test environment (staging) for a Developer Portal client (PS1): the config_text row, the sign-in host, the read-only Environment select, the amber pill
module: parasut
---

## Where is the environment kept?
- The site's, in the `config_text` row `parasut_environment`: `staging`, or no row for live (`parasut_environment()`; any other value reads as live). No table, no column.

## Which addresses does it change?
- The provider (`includes/connect/providers/parasut.php`) takes its authorize and token addresses and the issuer it checks from `parasut_sign_in()`: `id-staging.parasut.com` for staging, `id.parasut.com` for live, the same paths.
- `parasut_api_base()` answers `https://api.parasut.com` for both: Paraşüt's pages name no staging API address. The first connect on dev with the owner's staging client decides it; a different answer is one line there.
- The older way (a client from before the portal) has no staging: its Connect and exchange never read the environment.

## How is it chosen?
- Paraşüt's page of Connections, "Connection details", the portal client's boxes: the select "Environment" (Live, Test (staging)) after "Redirect address", with the help about a test client; saved with "Save" (`parasut_environment_save()` in the `save_app` branch).
- While a portal connection row is there, in any state, the select is disabled with "Disconnect to change the environment." and the save writes nothing (`parasut_environment_save_value()` answers null): a token of one environment is never used against the other.

## Where does the test mark show?
- `parasut_environment_mark()`: the amber pill "Test environment" only while connected (status active) in staging. On Paraşüt's page it stands before the status line; the e-document screens (ERP dashboard, invoice, settings card) read it through `erp_edoc_parasut_environment()`.

## What is not tested?
- A connect against `id-staging.parasut.com` and which API address answers a staging token: made by the architect on dev after PS1 landed (`plans/parasut-devhub-staging.md`).

Tests: `tests/parasut_devhub_staging_test.php`. Related: `erp-parasut-devhub-connection.md`, `erp-parasut-devhub-application.md`.
