---
name: encryption-key-reseal-registry
description: Reset Encryption Key re-seals what pg_reseal_registry() lists in the same save; entry fields, the four kinds, done/skipped/failed counts, the $key parameters of the sealing helpers
module: reseal
---

## What does a key reset seal again?
- `security.save.php` writes the new key with `pg_write_config_file()`, then calls `pg_reseal_run($old, $new)` (`includes/fn/reseal.php`) in the same request, while `ENCRYPTION_KEY` still holds the old key.
- It walks `pg_reseal_registry()`: one entry per table and column. Today: `orders.card_number` (kind `card`) and the gateway secrets of `pg_gateway_secret_fields()` (kind `gateway`). The module entries come with the plan's RK2 (`plans/encryption-key-reseal.md`).

## What does an entry hold?
- `area` (counts are grouped by it), `table`, `column`, `key` (primary key, `''` for the one-row `config`), `kind`, `where` (SQL picking the sealed rows; code, never input).
- Optional: `iv_column` for an `iv` value whose IV has its own column (the Google client secret's `oauth_google_secret_iv`), `width` for `gateway`, `json` for an `iv` value that is JSON.
- Kinds: `card` (`encrypt_credit_card_number()`), `gateway` (`pg_gateway_secret_seal()`), `iv` (`encrypt_string_with_iv()`, `"<ciphertext>:<iv>"` or the own column), `connect` (`pg_connect_seal()`).

## What do the counts mean?
- `pg_reseal_value()` (pure apart from the cipher) answers `done` with the new value, `skipped` when the value does not open with the old key (it was unreadable already and stays as it is), `failed` when it opened but could not be sealed.
- The write is `UPDATE … WHERE column = <value read> [AND key = …]`; a write that fails counts as `failed` and the row keeps its old value. The run never stops the save halfway: the new key is already on disk.
- `pg_reseal_run()` returns `areas` => area => done/skipped/failed and `seconds`, the time measured for the plan's open question (the request budget on a site with thousands of two-step keys). The save's notice shows the done counts and a warning the failed ones (`ui/settings-security-cards.md`); the card's lists are split by the registry's area names.
- A missing table or column counts as nothing sealed.

## Why is a JSON value checked after opening?
- `iv` and `connect` values are AES-256-CBC without a MAC: a wrong key passes the padding check about once in 256 tries with garbage. `connect` values must decode to an array; an `iv` entry with `json` too. A non-JSON `iv` value (a two-step key, a token) can still be re-sealed as garbage once in 256 when it was not sealed with the old key; it was unreadable before, so nothing readable is lost.

## Which helpers take a key?
- `encrypt_string_with_iv($plain, $key = null)`, `decode_ssl_keys($cipher, $iv, $key = null)`, `pg_connect_seal($data, $key = null)`, `pg_connect_unseal($stored, $key = null)`, `pg_connect_can_seal($key = null)`: `null` is `ENCRYPTION_KEY`, as before.
- `pg_ssl_keys_open($cipher, $iv, $key = null)` (`includes/authentication.php`, beside `decode_ssl_keys()`) answers `null` instead of `''` when the value does not open, so an empty plaintext is told from a failure. Without a key both answer nothing instead of failing on the undefined constant.

Related: docs/memory/commerce/payment-gateway-secrets.md, docs/memory/ui/settings-security-cards.md, docs/memory/core/security-helpers-and-secret-storage.md
