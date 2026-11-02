---
name: object-storage-client
description: The S3-compatible client (aws_sign.php SigV4, object_storage.php put/get/head/delete/list/multipart/presign), its answers, where its settings live, the Storage card and its test (PR #5349)
module: object_storage
---

## What signs a request?
- `includes/fn/aws_sign.php` (`aws-sigv4-signing.md`, #5348), loaded by `object_storage.php`: `pg_aws_authorization()` and `pg_aws_presign()` on a URL. The client calls them with `normalize` off (S3 paths are not normalised) and, for a file streamed from disk, the `payload_hash` option (its `hash_file()` digest). `pg_os_target()` builds the URL with its own RFC 3986 encoding (`pg_os_uri_encode()`, `pg_os_query()`) and drops a default port, as the signer names the host.
- The backup type `s3` sends through this client too (`backup-object-storage.md`).

## Which calls are there, and what do they answer?
- `includes/fn/object_storage.php`: `pg_os_put($config, $key, $file, $options)` (`data` sends a string; above 64 MB a multipart upload in 16 MB parts, one retry per part on a network or 5xx failure, abort on any failure, a completion that answers 200 with an `<Error>` body counts as failed), `pg_os_get($config, $key, $target)` (to a file through `sink`, a half-written file deleted; `null` reads into memory with a 1 MB cap), `pg_os_head()`, `pg_os_delete()` (a missing object counts as deleted), `pg_os_list($config, $prefix)` (ListObjectsV2, pages up to `limit`), `pg_os_presign($config, $key, $expires, $overrides)`, `pg_os_test()`.
- Keys are below the configured prefix; `pg_os_object_key()` adds it, `pg_os_list()` strips it.
- Every answer has `ok`; a failure is `pg_os_fail()`: `code` (the service's `<Code>`, `http_<status>`, `not_found`, `network`, `too_large`, `write_failed`, `read_failed`, `not_configured`, `endpoint`) and `message`. Nothing throws.
- Requests go through `pg_curl_run()` (https, proxy, `pg_curl_tls()`). A file body is streamed with its new `upload` option (`CURLOPT_UPLOAD` + `CURLOPT_INFILE`), never read into memory; its payload hash is `hash_file()`. An endpoint on http is admitted for that request only (`allow_http`), for a store on the same host or LAN; `check_address` stays off for the same reason.
- A body sent from memory always names its Content-Type (default `application/octet-stream`): without it libcurl sends its form type and the object is stored with it.

## Where are the settings?
- One `connect_apps` row, provider `object_storage` (`pg_os_app_key()`): access key id as `client_id`, the secret sealed (`pg_connect_app_save()`), endpoint, region, bucket, prefix, path style as `options`, `enabled` as the card's switch. No schema change. `pg_os_settings()` reads it, `pg_os_ready()` says whether calls can be made.
- No vendor is named in code: the endpoint is required (no default host); the region defaults to `us-east-1`.

## What does the Storage card do?
- Site Settings › Updates and maintenance › Storage (`pgset-storage`, `includes/settings/maintenance.php`). Drawn only when the site can seal (else a note); the hidden `object_storage_card` tells the save it was drawn. The secret box is drawn empty ("Saved"); empty keeps it.
- Switched on, or with "Save and test", `pg_os_config_errors()` refuses an incomplete or invalid set before anything is written (endpoint http/https without credentials or query, region, bucket rules, a bucket that cannot be a host name without path style, prefix without `..`). Switched off it is kept as typed.
- "Save and test" (named submit, `ui/settings-card-own-button.md`) saves, then writes, reads back, compares and deletes `.pinegrap-test-<random>.txt`; the outcome is a notice.

## What else changed beside it?
- `maintenance.save.php`: the chat notification targets' save block sat inside the backup test's `if` (a brace slip), so targets were written only when "Save and test the connection" of the backup card was pressed. Fixed with this PR.
