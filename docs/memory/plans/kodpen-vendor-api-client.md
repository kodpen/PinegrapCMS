---
name: kodpen-vendor-api-client
description: Plan (owner 2026-10-25 to 27): 2026.11.0 asks api.kodpen.com/v1 for licences and signed updates behind one switch line, no api2 fallback; steps P1-P8 in -steps, contract in the -design files
---

## What is the work, and where does it stand?
- Today the version check, licence check and widget 14 ask `www.kodpen.com/api2` with a fixed `API` key; the download takes a fixed-name ZIP. `api.kodpen.com/v1` goes live first; 2026.11.0 then ships a client in `includes/fn/vendor_api.php`, turned on by one line. Contract: `-design.md`, `-design-update.md`.

## What is decided?
Source: the vendor plan's section 9 and its owner decisions, numbered as there (Kodpen's private repository).
- v1 only, no api2 fallback; `download_assistant.php` stays the manual rescue path (decision 2, 2026-10-25). Widget 14 goes, id 14 never reused (3). ECDSA P-256 signed packages; no `openssl_verify()`, no update (7). 2026.11.0 ships only once the API is live and monitored (13).
- Licences bind to the name in `X-Pinegrap-Site`, finite site rights (15, 21); types demo, premium, enterprise (22); a closed account answers `suspended` (27). Only the basic inventory goes out (16).
- Beta only from pre-releases the owner opens on `development`, tag `v<open version>-beta.<YYYYMMDDHHMM>` (18).
- Key field: 16 of 0-9 and A-Z without I, L, O, U, any case, dashes optional; 16-digit keys work; a warning for local and private names; a test URL never changes the keys (27).
- Server side first, this round last (owner, 2026-10-26). Where section 9 and the vendor's bridge matrix differ (which 401 is `no_match`; "newer" or "other" build), the matrix and its vectors win.

## What is out of scope?
- `download_assistant.php` (root) with its api2 call and key; any schema change (the build id is a file); `waf.php`; `pg_curl_tls()` and `ALLOW_INSECURE_UPDATE_TLS`; `pg_extract_archive()`; hosted gates; Pinegrap AI's key use; the `VERSION === '2026'` block. The api2 branches go in the next open version.

## How does it meet the server's go-live?
- Every step merges with the switch off: api2 runs as today, only tests reach v1; 2026.4.x sites run none of it; hosted and demo sites keep their gates.
- P8 (owner's word, before the release branch) needs: vendor plan section 12 steps 1-6 done; P2-P7 merged; key fingerprints equal production's; packages signed only after the owner's own-clone check (vendor review finding 11); a licence check and an update passed on the vendor's docker API. Then one beta site updates from production.
- Until release day licences answer 503 `not_live` (card: technical reason, 15-min retry); stable offers nothing (404 or an older adopted release); beta offers the pre-release the owner points at.

## Which questions are open?
- Q1 (owner; P6 waits; P1's PR opens the `architect` issue): does a beta site without a build id (stable install, 2026.4.x updated) get the beta build of its own version? The matrix says yes, but that build may be older than the release or newer: the client cannot tell. Recommendation: only over a build file naming a beta build; Repair still installs the pointer's build, named on screen.
- To record (P1's PR asks the architect; decisions list; P7 waits): widget 14 retired in 2026.11.0 with its Subscription ID field, column kept (decision 3); `download_assistant.php` keeps its api2 call and key (2).
- Open on the vendor side, no step waits: the old fixed beta download on release day (how many sites meet Q1); KVKK erasure (P7's note promises none); an outbox "try now".
- Inputs: the two public P-256 keys, lower-case ids (P5c; P8 waits); the primary id as P2's repository variable.

## Where to start?
- After the claim check: P3; P4 and P5a (different files: side by side only if the owner allows or the coordinator chooses); P5b, P5c, P6, P7. P2 (`.github/`, `docs/` only) may run beside a step on the same terms.

Related: docs/memory/platform/license-check.md
