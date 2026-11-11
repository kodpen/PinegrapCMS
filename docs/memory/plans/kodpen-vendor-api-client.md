---
name: kodpen-vendor-api-client
description: Plan (owner 2026-10-25 to 27): the Kodpen API client is on (P8); left are P2 and P5c before the 2026.11.0 release branch and P9, the api2 client removal in the next open version; steps in -steps
---

## Where does it stand?
- 2026.11.0 asks `api.kodpen.com/v1` for licences and signed updates: the client in `includes/fn/vendor_api.php` is on since P8 (#8653). Contract: `platform/vendor-api-client.md` and its siblings; the open parts in `-design-update.md`.
- The api2 client branches are still in the code but no longer reached; setting the switch to false is the only way back to them, until P9 removes them.

## What is decided?
Source: the vendor plan's section 9 and its owner decisions, numbered as there (Kodpen's private repository).
- v1 only, no api2 fallback; `download_assistant.php` stays the manual rescue path with its api2 call and key (decision 2, #3205). ECDSA P-256 signed packages; no `openssl_verify()`, no update (7). 2026.11.0 ships only once the API is live and monitored (13).
- Licences bind to the name in `X-Pinegrap-Site`, finite site rights (15, 21); types demo, premium, enterprise (22); a closed account answers `suspended` (27). Only the basic inventory goes out (16).
- Beta only from pre-releases the owner opens on `development`, tag `v<open version>-beta.<YYYYMMDDHHMM>` (18).
- Key field: 16 of 0-9 and A-Z without I, L, O, U, any case, dashes optional; 16-digit keys work; a warning for local and private names; a test URL never changes the keys (27).
- Where section 9 and the vendor's bridge matrix differ (which 401 is `no_match`; "newer" or "other" build), the matrix and its vectors win.
- Owner (2026-11-11): the switch goes on now ("close the api2 topic"), before the old P8 gate held: the server's go-live steps, P2 and P5c merged, key fingerprints equal production's, a licence check and an update on the vendor's docker API (`workflow/decisions-made-product-43.md`).
- The api2 client branches keep #3205's timing: they leave in the next open version (P9).

## What is out of scope?
- `download_assistant.php` (root) with its api2 call and key; any schema change (the build id is a file); `waf.php`; `pg_curl_tls()` and `ALLOW_INSECURE_UPDATE_TLS`; `pg_extract_archive()`; hosted gates; Pinegrap AI's key use; the `VERSION === '2026'` block.

## What must land before the 2026.11.0 release branch?
- P2 and P5c. With the switch on and `pg_vendor_release_keys()` empty, the licence check works and the update check says no update while `/releases/latest` offers nothing, but any offered release is refused at `unknown_key_id`: a 2026.11.0 without keys never updates from the panel, only through `download_assistant.php`. P8 could merge first only because nothing is offered yet.
- Still to prove before the release: a licence check and an update on the vendor's docker API; key fingerprints equal production's; packages signed only after the owner's own-clone check (vendor review finding 11); then one beta site updates from production.
- A 503 `not_live` licence answer shows the technical-reason card (15-min retry); stable offers nothing until a signed release (404 or an older adopted release); beta offers the pre-release the owner points at.

## Which questions are open?
- Open on the vendor side, no step waits: the old fixed beta download on release day (how many sites meet Q1, `platform/update-beta-builds.md`); KVKK erasure (P7's note promises none); an outbox "try now".
- Inputs: the two public P-256 keys, lower-case ids (P5c); the primary id as P2's repository variable.

## Where to start?
- After the claim check: P5c, then P2 (`.github/`, `docs/` only; it may run beside a step). P9 waits for the next open version.

Related: docs/memory/platform/license-check.md
