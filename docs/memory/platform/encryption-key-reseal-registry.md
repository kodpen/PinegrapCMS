---
name: encryption-key-reseal-registry
description: Reset Encryption Key re-seals what pg_reseal_registry() lists, last in the same save; the entries, kinds and JSON fields, done/skipped/failed counts, the $key parameters of the sealing helpers
module: reseal
---

## What does a key reset seal again?
- The key-reset block is the last thing `security.save.php` does: `pg_write_config_file()`, then `pg_reseal_run($old, $new)` (`includes/fn/reseal.php`), while `ENCRYPTION_KEY` still holds the old key. Every write before it in that save (Apple's application, the CAPTCHA keys, the Google client secret) seals with the old key and is carried over with the rest.
- `pg_reseal_registry()` lists: `orders.card_number`, the gateway secrets, `connect_apps` / `connect_accounts.secret`, `marketplace_accounts.credentials`, `erp_edoc_providers.credentials_enc`, config `parasut_credentials_enc`, `backup_remote_settings`, `product_feeds` (token_secret of each format), `oauth_google_client_secret` (+ its IV column), `translation_google_key`, `ws_claude_token`; `user_mfa` totp_secret and pending_secret; `subscriptions.card_ref` (sealed shape only); `ws_mcp_servers.secret`, `ws_calendar_feeds.token_secret`, `ws_mail_boxes.settings` (its `secret`); config_text `ap_keys` (`private`), `ws_voice_key`, `ws_voice_speech_key`.
- A new value sealed with the site's key gets an entry here; `tests/reseal_test.php` checks each named table and column against the migration steps.

## What does an entry hold?
- `area` (counts are grouped by it), `table`, `column`, `key` (primary key, `''` for the one-row `config`), `kind`, `where` (SQL picking the sealed rows; code, never input).
- Optional: `iv_column` for an IV in its own column, `width` for `gateway`, `json` for an `iv` value that is JSON, `fields` (paths such as `formats.*.token_secret`) for sealed values inside a JSON column, `json_flags` for how that column is written.
- Kinds: `card` (`encrypt_credit_card_number()`), `gateway` (`pg_gateway_secret_seal()`), `iv` (`encrypt_string_with_iv()`), `connect` (`pg_connect_seal()`).

## What do the counts mean?
- `pg_reseal_value()` answers `done`, `skipped` (does not open with the old key: unreadable already, left as it is) or `failed` (opened, could not be sealed); a `fields` entry counts each value inside.
- The write is `UPDATE … WHERE column = <value read> [AND key = …]`; a failed write counts as failed. The run never stops halfway: the new key is already on disk.
- `pg_reseal_run()` returns `areas` => area => done/skipped/failed and `seconds` (the plan's request-budget question). A missing table or column counts as nothing. The save's notice shows the done counts and a warning the failed ones (`ui/settings-security-cards.md`); the card's lists are split by the registry's area names.

## Why is a JSON value checked after opening?
- `iv` and `connect` values are AES-256-CBC without a MAC: a wrong key passes the padding check about once in 256 tries with garbage. `connect` values and `json` entries must decode to an array. A non-JSON value that was not sealed with the old key can be re-sealed as garbage; it was unreadable before, so nothing is lost.

## Which helpers take a key?
- `encrypt_string_with_iv()`, `decode_ssl_keys()`, `pg_connect_seal()`, `pg_connect_unseal()`, `pg_connect_can_seal()`: a last `$key = null`, null is `ENCRYPTION_KEY`. `pg_ssl_keys_open()` answers `null` instead of `''` when a value does not open.

Related: docs/memory/commerce/payment-gateway-secrets.md, docs/memory/ui/settings-security-cards.md
