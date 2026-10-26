---
name: api-connected-accounts-usage
description: Connected accounts at work: mail through Gmail or Microsoft 365, cloud backups to Drive, OneDrive or Dropbox, a Connect button inside a settings form, response headers, what api_http_request() refuses
---

## How does mail use a connected account?
- `includes/connect/purposes/mail.php` carries the delivery details per provider (`send`); delivery code is `pg_mail_connection*()` in `includes/fn/mail.php`. A new mail provider = a provider file + its `send` entry.

## How do cloud backups use one?
- `includes/connect/purposes/backup.php`, code in `includes/fn/backup_cloud.php`.
- The Connect button sits inside the settings form but must not submit it: it carries `form="..."` and submits a hidden form placed outside (`$pg_settings_modals[]`). The return address is `pg_settings_url('general')` + `?backup_connect=<type>#pgset-backup`, and the screen takes the message with `pg_connect_flash_take()` only when that parameter is there.
- A large file goes in 10 MiB parts, each through `pg_connect_api()`, so a token that runs out half-way is renewed between parts. A Graph upload session's `uploadUrl` gets no token: it is called with `api_http_request()` directly.

## How is a response header read?
- An outgoing call that needs one (a resumable upload's `Location`, an MCP server's session) passes `'response_headers' => true` to `api_http_request()`; the answer then carries `headers` (lower-case name => value, built by `api_http_header_line()`: the last of a repeated name wins, except WWW-Authenticate, whose lines are joined with ", " so a Bearer challenge after a Basic one is still read).
- Every answer also carries `errno`: curl's error number when curl failed (28 a timeout), else 0, also for an address refused before curl ran. `error` stays curl's words plus the TLS hint; the MCP client turns 28, 7 and 6 into sentences (`api-mcp-client.md`).

## What does api_http_request() refuse? (`includes/api/outbound/http.php`)
- Addresses inside the network (private, loopback, reserved, the cloud metadata address), redirects (a second address no check has seen), unverified certificates and plain http (webhooks may allow it with `API_WEBHOOK_ALLOW_HTTP`). The host is resolved once and curl is pinned to that address. Webhooks, marketplace connectors, connected accounts and the OAuth server's CIMD reads all go through it.
