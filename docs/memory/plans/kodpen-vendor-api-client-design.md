---
name: kodpen-vendor-api-client-design
description: Client contract of plans/kodpen-vendor-api-client.md: calls and headers, the licence check, card and key field, what is stored where, the switch line; updates in -design-update
---

## Which calls, with what?
- Base `https://api.kodpen.com/v1`, or `PG_VENDOR_API_URL` from `data/config.php` (https, with `/v1`, tests only; any other value: no call, never production).
- Every call through `pg_curl_run()`, no redirect; no cURL: no call. `X-Pinegrap-Site` = `HOSTNAME_SETTING` as it is; with a control byte or over 253 bytes, release calls and downloads go without it and the licence call is skipped as unusable.
- `X-Pinegrap-Env: edition=…; php=…; db=…; server=…; channel=…` from `EDITION`, `PHP_VERSION`, `pg_db_server_version(mysqli_get_server_info(db::$con))['label']`, `SERVER_SOFTWARE` up to its first space or `(` ('' when unset), `pg_update_channel()`; no `;`, CR or LF in a value, under 512 bytes. Never uname, URL, OS, installer flag.
- JSON calls: `Accept: application/json, application/problem+json`, connect 4 s, total 8 s. Package: 120 s.
- `VERSION` goes out (body `version`, query `from`) only when it matches `^\d{4}\.\d{1,2}\.\d{1,3}$`.
- A problem is the status plus `code` of an `application/problem+json` object; never `title`, `detail`, `Retry-After`.
- Per case: `tests/fixtures/vendor_api/`. Docker try (P8): the docker `api.base_url` is the https layer's origin, its CA through `CURL_CA_BUNDLE`.

## How is the licence asked (POST /licenses/verify)?
- `Authorization: Bearer <key>`, the key without dashes and blanks, upper-cased. Not 16 of the alphabet then: no call, `error`/`no_match` (12 h). No key, no call. Body `{"version", "channel"}`. The built request equals the vectors' `request` block.
- 200 active or expired: `success`, '', `valid_until`; 200 suspended: `error`, `suspended`; 401 `invalid_license`: `error`, `no_match`; 403 `seats_full`, `access_blocked`: `error`, that code; else status ''. Type and sites only from a schema-valid 200.
- Card: the vectors' `card`, an own text for `suspended`; Check License on every error. `license-check.md` notes that 10 `invalid_license` an hour close the licence check for the whole address, valid keys too.
- Key field: decision 27's alphabet; save strips dashes and blanks, upper-cases, refuses nothing; a real key change or a hostname change unsets the licence array.
- Switch on only, by the key: "will spend a site right" when the canonical name is `localhost`, ends in `.test` or `.local`, or is in 10/8, 172.16/12, 192.168/16 or 127/8; "licence checks will fail" when it has none (non-ASCII without intl: no text). Test: every `local` vector warns, every other non-null one does not.
- Switch on only, by the channel: checks send the site name, the server IP and the Env values to Kodpen; no erasure promised.

## What is stored where?
- Session `software.settings.license`: today's keys (status, error_code, countdown, expiration_date_formatted, last_check, diag_*) plus type, sites_used, sites_total and `source` 'v1'. `P_end_d` lives only in `pg_license_map_v1()`'s answer. With the switch on, an array without `source` is asked again at once (an api2 answer left in the session by the update).
- Session `software.update` (the pin), the package in `data/temp/`, `data/update_build.json`: `-design-update.md`.
- The switch, alone on its line in `vendor_api.php`: `define('PG_VENDOR_API_V1', false);`, `true` from P8; the on form nowhere else in the file, comment blocks included (the hosting platform reads the file as text: `/^[ \t]*define\('PG_VENDOR_API_V1', true\);[ \t]*\r?$/m`).
- Modules: P3 and the keys in `vendor_api.php`; `pg_license_map_v1()` in `output.php`; the update functions in `update.php`. P4 and P5a never edit `vendor_api.php`.
