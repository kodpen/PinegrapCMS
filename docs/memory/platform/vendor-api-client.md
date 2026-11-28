---
name: vendor-api-client
description: Kodpen API client base (includes/fn/vendor_api.php, PR #3255): the PG_VENDOR_API_V1 line (platform only), api2 gone (P9), base URL, headers, timeouts, problem reader, canonical site name, vector sums
module: vendor_api
---

## What is it?
- What every call to `api.kodpen.com/v1` is built from; licence check and update are built on it; the plan's decisions: `vendor-api-decisions.md`. Loaded by `functions.php`.

## What does the switch do?
- `define('PG_VENDOR_API_V1', true);` alone on its line, on since #8653 (2026-11-11). The hosting platform reads the file as text (`/^[ \t]*define\('PG_VENDOR_API_V1', true\);[ \t]*\r?$/m`), so the on form is on that line only (comments too), the off form nowhere; `vendor_api_test.php` pins it.
- No code reads the constant since P9 (#12212); the line stays for the platform. No way back to api2.

## Is the api2 server still asked?
- Not from `pinegrap/` since P9 (#12212): licence check, update check and steps, Repair ask only v1; what went: `vendor-api-decisions.md`.
- Owner (2026-11-28): the api2 branches go now, in 2026.11.0. In the owner's words: "api2 dalları kalkabilir artık yazılım yeni api üzerinden haberleşecek, api.kodpen.com devrede".
- The root `download_assistant.php` keeps its api2 call and key for good (#3205). `output_test.php` pins no `kodpen.com/api2` and no switch read under `pinegrap/`.

## Where does a call go?
- `pg_vendor_api_base()`: `https://api.kodpen.com/v1`, or `PG_VENDOR_API_URL` (`data/config.php`, a test server): https, host, optional port and path, ending in `/v1`, no user, query or blank. Any other value gives '' and no call, never production. `pg_vendor_api_url($path)` appends the path.
- The vendor's docker API: `PG_VENDOR_API_URL` is its `api.base_url` (the https origin), its CA in `CURL_CA_BUNDLE`.

## What does every call carry?
- `pg_vendor_api_request($method, $url, $options)`: `pg_curl_run()` with `pg_vendor_api_options()`: no redirect, connect 4 s, total 8 s (`timeout`; the package passes 120), body cap 64 KB (`cap`), `X-Pinegrap-Site`, `X-Pinegrap-Env`. A JSON call (default) sends `Accept: application/json, application/problem+json`, a `body` `Content-Type: application/json`; `json` false (signature, package) drops the Accept line; `sink` writes the body to a file; `site_required` (the licence check) calls nothing when the site name cannot go out.
- `X-Pinegrap-Site` = `HOSTNAME_SETTING` as it is (`pg_vendor_api_site_value()`); empty, over 253 bytes or with a control byte: left out.
- `X-Pinegrap-Env` (`pg_vendor_api_env()`): `edition=…; php=…; db=…; server=…; channel=…` from `EDITION`, `PHP_VERSION`, `pg_db_server_version()`'s label, `SERVER_SOFTWARE` up to its first space or `(`, `pg_update_channel()`; `;` and control bytes removed, a value cut to 64 bytes, so under 512.
- `pg_vendor_api_version()`: `VERSION` only when it matches `^\d{4}\.\d{1,2}\.\d{1,3}$` (the installer's `VERSION` is `true`), else '' and the call leaves it out.

## How is an error answer read?
- `pg_vendor_api_problem($answer)`: status 400-599, media type `application/problem+json` (`pg_vendor_api_media_type()`), a JSON object whose `code` is a lower-case name: `array(status, code)`; else null (HTML page, empty body). `title`, `detail`, `Retry-After` are never read.

## What is the canonical site name?
- `pg_vendor_api_canonical_site()` runs `site_host.json`'s six steps, `\z` anchored (a final line break is refused): the name, null when refused, false for a non-ASCII name without intl (`idn_to_ascii()` UTS46). `pg_vendor_api_site_local()`: localhost, `.test`, `.local`, 10/8, 172.16/12, 192.168/16, 127/8, for the key field's warning.

## Which vectors and tests?
- sha256 of `tests/fixtures/vendor_api/`: `licence_mapping.json` 82bf0e49…, `site_host.json` c6f8e9ca…, `update_steps.json` 4116b706…; `vendor_api_test.php` pins them in full.
- `tests/vendor_api_test.php` (on-form pin, refusals before cURL: no address, no site name); the cap and sink in `curl_run_test.php` (`curl-run-wrapper.md`).
