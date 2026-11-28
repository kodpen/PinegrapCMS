---
name: file-integrity-github-reference
description: Integrity reference from 2026.4.5: the release tag's tree on GitHub (git blob SHA-1), cache, release rules, what 2026.4.4 sites use, cacert.pem refresh, headers read without $http_response_header.
---

## Where does the reference come from?
- `pg_integrity_github_reference()` asks `api.github.com` for a tag's tree in `kodpen/PinegrapCMS` and keeps the blobs under `pinegrap/` in scope. Tags tried (`pg_integrity_release_tags()`): the `git_ref` of `data/update_build.json` (`pg_integrity_build_ref()`), then `v<version>`.
- The build file's ref counts on either channel since PR #PRNUM: a site switched back to Stable keeps the beta's files until a stable release replaces them, and against `v<version>` it showed a false "missing or tampered" alarm. `v<version>-beta` is no longer tried: no such tag exists, a pre-release is `v<version>-beta.<YYYYMMDDHHMM>`.
- A beta install without the build file (the old updater's hop, a hand-installed full package) is checked against `v<version>`: yellow `not_released` until the stable tag exists, then a mismatch until Repair Software installs a build.
- Cached in `data/temp/hash_reference_github.json` under `pg_integrity_reference_key()` (version, plus `@<git_ref>` when one is asked; a cache without the key counts as its version), so another build is fetched anew; again every 12 hours (a failed attempt not within the hour for the same key), so a cache an intruder rewrote is replaced.
- The request uses the update download's certificate checks (`pg_curl_tls()`) and never falls back to an unverified connection.
- Files are compared by git blob SHA-1 (`pg_integrity_git_blob_matches()`): the file as on disk, with CRLF read as LF, or with every line ending CRLF (Git for Windows, FTP in ASCII mode).
- No tag (`not_released`), a truncated tree or no answer: `unable_to_fetch_reference`, yellow in the widget.
- Why: the old reference was hashed from the dev working tree and published on kodpen.com; it listed unpushed files, files being edited and leftovers, and every site installed from GitHub saw them as tampering. The tag cannot carry any of that.

## What does a release have to do?
- Nothing for the reference: none is generated or uploaded. The package is built from the tag and the tag is NEVER moved after the release; whatever the tag holds is the reference.
- Sites still on 2026.4.4 and earlier compare their files with `pinegrap_hash_referance[VERSION].json` on kodpen.com (byte-based SHA-256; a local reference not stamped with their own host is replaced by it every 12 hours). Leave the `[2026.4.4]` copy there as it is.

## Must cacert.pem be refreshed before a release?
- Yes. `pinegrap/data/cacert.pem` (the copy the operator points to with `CURL_CA_BUNDLE`) and `includes/iyzipay-php/cacert.pem` (used by the iyzipay client itself via `CURLOPT_CAINFO`) are the Mozilla root bundle: replace both with the current `https://curl.se/ca/cacert.pem`, byte for byte identical.
- `data/` is outside the scope; the iyzipay copy is inside `includes/`, so it changes only with a release (tag), and after replacing it on the dev machine the dev reference is generated again.

## How does the GitHub fetch read the answer without cURL?
- `pg_integrity_http_get()` opens the address with `fopen()` and takes the status lines from `stream_get_meta_data()['wrapper_data']`. Until #784 it read `$http_response_header`; PHP 8.5 deprecates that variable and reports a read of it when the file is compiled, so `includes/fn/system_status.php` printed "Deprecated" at the top of panel pages although cURL answered every request. `import_design_fetch_once()` reads its headers the same way, and `tests/http_response_header_test.php` fails on any read of the variable outside `includes/dompdf/` (dompdf assigns it before reading, which PHP 8.5 accepts).

Related: docs/memory/core/file-integrity-check.md
