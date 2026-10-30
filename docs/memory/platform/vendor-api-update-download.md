---
name: vendor-api-update-download
description: Download, backup and replace of a signed update behind PG_VENDOR_API_V1 (PR #3632): pg_vendor_update_steps(), fetch order, package file, refusals, security log, manifest shape
---

## What runs after the check step?
- `includes/panel/software.php` `download`, `backup`, `replace` read only `$_SESSION['software']['update']` (`pg_panel_software_update_pin()`; none: "Start the update again"). With the switch off they run api2's fixed package as before.
- `pg_vendor_update_download($pin)` (`includes/fn/update.php`) runs the pure `pg_vendor_update_steps($pin, $site, $keys, $observed, $from)` after each fetch: signature, manifest, download, package, zip_magic, zip_version (`update_steps.json`). A step whose observation is missing answers `pending`, so nothing is fetched before the checks ahead of it pass: manifest and signature addresses (`pg_vendor_update_url_ok()`: https, the base's host and effective port, no user info, backslash, blank or control byte), OpenSSL present and a known key id come before any fetch; the package URL before the package.
- Manifest cap 64 KB, signature 4 KB, package capped at the pinned size into `data/temp/<package_file>` opened with `x`. A non-200 manifest or signature counts as no signature (`signature_invalid`).
- `pg_vendor_update_verify()`: blanks stripped, strict base64, `openssl_verify(..., OPENSSL_ALGO_SHA256)`; only `=== 1` passes.
- "Newer" is judged only at zip_version: newer than VERSION, or equal for Repair or for another beta build (installed build is '' until P6's build file).

## What is the manifest read as?
- `pg_vendor_manifest_read()`: JSON with `schema` 1, `version`, `channel`, `build` ('' when absent) and `artifacts`, a list whose one entry of `kind` `update` gives size (int) and sha256; none or two is no manifest (`manifest_mismatch`). The vendor tool's real output is to be checked against this in P8's docker try.

## What do backup and replace do?
- Backup passes the pin's package to `pg_update_backup_create()`. Replace runs `pg_vendor_update_recheck()` (steps from `package`: size, sha256, in-ZIP version from the file on disk) right before `pg_extract_archive()`, so a file swapped after the download step is refused.
- `pg_vendor_update_forget()` deletes the run's own package and clears the pin after replace and after any refusal or failure; the screen's retry starts at the check, which names a new file.

## Which failures are logged how?
- Download failure (cURL error, short body, a failed write): today's texts ("Error while get files…" with `pg_curl_tls_hint()`, "cut short", "disk may be full"), no security line.
- Refusal: `pg_vendor_update_refusal_message($reason)`, one text per reason. `pg_vendor_update_security_event()` marks the log line "refused as a security event" for a complete answer that is not the pinned release (bad signature, unknown key, manifest, foreign host or status, package, not a zip, in-ZIP version); not for `signature_unverifiable` or `not_newer`. A body past the cap counts as another size.

## Which keys?
- `pg_vendor_release_keys()` is empty until P5c, so every key id is unknown and nothing installs with the switch on.

## Tests?
- `tests/vendor_update_steps_test.php`: every vector case after the check, the pending order, address rules, manifest shapes, a P-256 pair made with `pg_openssl_key_new()`, a ZIP built in the test, a file swapped before replace. The network path itself is not unit tested.
