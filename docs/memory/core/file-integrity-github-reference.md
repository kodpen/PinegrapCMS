---
name: file-integrity-github-reference
description: 2026.4.5+ integrity check using the GitHub tag tree (git blob SHA-1), its cache and failure state, plus the pre-release cacert.pem refresh rule.
---

## What is the reference source from 2026.4.5?
- The kodpen.com rules in `file-integrity-check` apply only to sites running 2026.4.4 and earlier.
- The 2026.4.5 check uses neither the local `hash_reference.json` nor kodpen.com anywhere except the dev machine. `pg_integrity_github_reference()` fetches the tree of tag `v<version>` (beta channel: first `v<version>-beta`) from `api.github.com`, caches it in `data/temp/hash_reference_github.json`, refreshes every 12 hours, and compares files by git blob SHA-1 (as-is / CRLF->LF / all CRLF).
- On the dev machine the local `_generated`-stamped reference (SHA-256) stays valid as-is.
- Tag missing or GitHub unreachable -> result `unable_to_fetch_reference`, yellow in the widget.
- Why: a reference generated from the dev working tree carried leftovers, unpushed files and uncommitted changes; every site installed from GitHub saw them as tampering.
- Release rule: the tag is NOT moved after release, the package is built from the tag; no reference is uploaded to kodpen.com.

## Must cacert.pem be refreshed before a release?
- Yes. `pinegrap/data/cacert.pem` (the copy the operator points to with `CURL_CA_BUNDLE`) and `includes/iyzipay-php/cacert.pem` (used by the iyzipay client itself via `CURLOPT_CAINFO`) are the Mozilla root bundle.
- Before a release replace both with the current `https://curl.se/ca/cacert.pem`; they must be byte-for-byte identical.
- `data/` is outside the hash scope, but the iyzipay copy is inside `includes/`, so rerun `_software_create_hash.php` after replacing it.

## How does the GitHub fetch read the answer without cURL?
- `pg_integrity_http_get()` opens the address with `fopen()` and takes the status lines from `stream_get_meta_data()['wrapper_data']`. It used to read `$http_response_header`; PHP 8.5 deprecates that variable and reports a read of it when the file is compiled, so `includes/fn/system_status.php` printed "Deprecated" at the top of panel pages although cURL answered every request. `import_design_fetch_once()` reads its headers the same way, and `tests/http_response_header_test.php` fails on any read of the variable outside `includes/dompdf/` (dompdf assigns it before reading, which PHP 8.5 accepts).

Related: docs/memory/core/file-integrity-check.md
