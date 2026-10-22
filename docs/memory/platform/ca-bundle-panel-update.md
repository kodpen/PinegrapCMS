---
name: ca-bundle-panel-update
description: Panel CA bundle update (ca_bundle_update, pg_ca_bundle_update): https-only source, validation checks, atomic write to data/cacert.pem.
---

## Where is it and who can run it?
- The System Status card, "Maintenance and Tools" column, row "CA sertifika paketi" lists the state of `data/cacert.pem` (Mozilla header date, root count, whether `CURL_CA_BUNDLE` points to this file / another file / is undefined so the system store is used, source URL) and offers an Update button to the administrator only (role 0).
- Endpoint `api.php` -> `ca_bundle_update` (role 0 + `validate_token()`); work in `pg_ca_bundle_update()` (`includes/fn/update.php`, next to `pg_ca_bundle_status()`, `pg_ca_bundle_inspect()`, `pg_ca_bundle_source_url()`).

## What rules does the update follow?
- Source is https only. Default `https://curl.se/ca/cacert.pem`; a mirror may be set with `CA_BUNDLE_SOURCE_URL` in `config.php`, a non-https value is rejected before download. Redirects are limited with `CURLPROTO_HTTPS`. No URL or path is taken from the user.
- The download is verified with `pg_curl_tls()` (same rule and reason as the update channel: this file decides what is trusted from now on).
- Validate before believing: size 50 KB-2 MB; header `## Certificate data from Mozilla as of:` parseable; at least 100 roots; every PEM block passes `openssl_x509_read()`; then the date comparison: a file OLDER than the installed header date is rejected (the mirror may lag), the same date returns "already current" and the file is untouched; if the installed file has no header no date condition applies. Structural checks run BEFORE the date so a corrupt file is reported as corrupt, not as "already current".
- Atomic write: `tempnam()` inside `data/temp/`, permissions copied from the installed file, `rename()` over the target; if `data/temp` is not writable (tempnam would escape to system temp) it is rejected. Then delete `data/temp/system_status_cache.json` and write `log_activity()`.
- The target is ALWAYS `data/cacert.pem`. If `CURL_CA_BUNDLE` points elsewhere, `data/cacert.pem` is still written and the row says the running config does not read this file.
- Never touch `includes/iyzipay-php/cacert.pem`: `includes/` is in the integrity digest; that copy changes only with the software version. The screen text says so in one sentence.

Related: docs/memory/platform/update-tls-verification.md
