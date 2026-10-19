---
name: security-helpers-and-secret-storage
description: CSRF/escape helpers (get_token_field, validate_token_field, h) and how secret config fields are stored encrypted as one blob, never exposed as constants.
---

## Which security helpers exist?
- `get_token_field()` outputs the CSRF token input. `validate_token_field()` validates the token on POST. `h($val)` is HTML escape (htmlspecialchars).

## Does lang() escape its {var:N} values?
- No. `lang(array('string' => '... {var:1}', 'vars' => $x))` inserts `$x` as is, and `liveform` prints notices, warnings and errors as raw HTML. A stored name or URL that goes into a notice must be wrapped: `'vars' => h($name)`. Symptom fixed in #541: an anonymous OAuth dynamic registration (`pg_oauth_register()`) could plant `<img onerror>` in `client_name`, which ran in the panel when a manager removed or re-enabled the client on `api_settings.php`; the same shape covered device names and webhook URLs there and the ended connection on `connected_apps.php`.

## How are secret-bearing config fields stored?
- A secret config field is NEVER opened into a constant. Paraşüt's secret key and password live in `config.parasut_credentials_enc` as ONE encrypted JSON blob (`"<ciphertext>:<iv>"`, the pair returned by `encrypt_string_with_iv()`); `init.php` only takes the blob into a constant.
- Decryption happens in the request that actually calls Paraşüt, via `_parasut_credentials()`.
- The same pattern is used in `mp_credentials_encode()` / `mp_credentials_decode()` (`includes/api/outbound/connectors/base.php`): a single column, so a new provider field needs no schema change.
- The settings screen does NOT render the value back; an empty box means "keep the saved value".
- `api_settings.php` does not encrypt: it hashes with `hash_hmac`. That pattern cannot be used for a credential that must be read back.

Related: docs/memory/core/file-integrity-check.md
