---
name: vendor-api-decisions
description: The Kodpen vendor API client's lasting decisions (licence binding, key format, signed packages, beta tags) and what is still to do before the 2026.11.0 release; the plan ended with P9
---

## Where does the Kodpen API client stand?
- Done (P1 to P9, last #12212): licence check and signed updates ask only `api.kodpen.com/v1` (`vendor-api-client.md`, `license-check.md`, `vendor-api-update.md`, `vendor-api-update-download.md`); releases get their packages (`release/release-packages.md`, #10350); the keys ship since #10348 (`vendor-api-release-keys.md`).
- P9 removed `pg_license_request_options()`, `pg_update_request_key()`, `pg_update_package_file()`, `pg_update_version_newer()` and `pg_update_repair_decision()` (its checks-off text is now `pg_update_repair_checks_off_message()`); the switch line stays (`vendor-api-client.md`).
- Source of the numbered decisions below: section 9 of the vendor plan and its owner decisions (Kodpen's private repository).

## What is decided?
- v1 only, no api2 fallback; `download_assistant.php` stays the manual rescue path with its api2 call and key (decision 2, #3205). ECDSA P-256 signed packages; no `openssl_verify()`, no update (7). 2026.11.0 ships only once the API is live and monitored (13).
- Licences bind to the name in `X-Pinegrap-Site`, finite site rights (15, 21); types demo, premium, enterprise (22); a closed account answers `suspended` (27). Only the basic inventory goes out (16).
- Beta only from pre-releases the owner opens on `development`, tag `v<open version>-beta.<YYYYMMDDHHMM>` (18).
- Key field: 16 of 0-9 and A-Z without I, L, O, U, any case, dashes optional; 16-digit keys work; a warning for local and private names; a test URL never changes the keys (27).
- Where section 9 and the vendor's bridge matrix differ (which 401 is `no_match`; "newer" or "other" build), the matrix and its vectors win.
- Out of the client's scope: any schema change (the build id is a file), `waf.php`, `pg_curl_tls()` and `ALLOW_INSECURE_UPDATE_TLS`, `pg_extract_archive()`, hosted gates, Pinegrap AI's key use, the `VERSION === '2026'` block.

## What must happen before the 2026.11.0 release?
- With no signed package `/releases/latest` offers nothing and the update check says no update: an unsigned 2026.11.0 updates only through `download_assistant.php`.
- Still to do: a licence check and an update on the vendor's docker API; key fingerprints equal production's; packages signed only after the owner's own-clone check (vendor review finding 11); then one beta site updates from production.
- A 503 `not_live` licence answer shows the technical-reason card (15-min retry); stable offers nothing until a signed release (404 or an older adopted release); beta offers the pre-release the owner points at.

## Which questions are open on the vendor side?
- The old fixed beta download on release day (how many sites meet it, `update-beta-builds.md`); KVKK erasure (the update channel card promises none); an outbox "try now". No Pinegrap step waits on them.

Related: docs/memory/platform/vendor-api-client.md
