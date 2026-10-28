---
name: security-helpers-and-secret-storage
description: CSRF helpers; secret config fields (one encrypted blob, never a constant, never shown back, the written exception); session cookie flags and sign-in renewal; the install unlock throttle.
---

## Which security helpers exist?
- CSRF: `get_token_field()` prints the token input in a form; `validate_token_field()` checks it on POST (it prints an HTML error page, so a JSON endpoint compares the token itself: `json-endpoint-rules.md`).
- Output: `h($val)` for every value in HTML; the rest of the escaping rules: `output-escaping.md`.

## How are secret-bearing config fields stored?
- A secret config field is NEVER opened into a constant. Paraşüt's secret key and password live in `config.parasut_credentials_enc` as ONE encrypted JSON blob (`"<ciphertext>:<iv>"`, the pair returned by `encrypt_string_with_iv()`); `init.php` only takes the blob into a constant.
- Decryption happens in the request that actually calls Paraşüt, via `_parasut_credentials()`.
- The same pattern is used in `mp_credentials_encode()` / `mp_credentials_decode()` (`includes/api/outbound/connectors/base.php`): a single column, so a new provider field needs no schema change.
- The external API's key secrets (made on `api_settings.php`) are not encrypted but hashed: `api_secret_hash()` (`includes/api/keys.php`, `hash_hmac`), right for a secret that is only compared. Never copy that pattern for a credential that must be read back: a hash cannot be recovered.

## May a settings screen show a stored secret?
- No. A new secret field is drawn empty; an empty box keeps the saved value (the Paraşüt card and the marketplace connectors do this).
- The legacy gateways' secrets are sealed and drawn as a mask: `commerce/payment-gateway-secrets.md`.
- Still printed: the TSA password (`includes/settings/features.php`), the e-document provider credentials (`includes/settings/prep.php`, where an emptied box clears the value through `erp_edoc_credentials_save()`) and other gateways' keys (Stripe, Sage, ClearCommerce): legacy, never a pattern to copy. The Google Client Secret is the one recorded exception (operator preference, #62 item 13, `workflow/decisions-made-access.md`). Site Settings needs manager rights.
- No fake password field in a dialog; `pg_settings_stamp_autocomplete()` prints the password managers' opt-outs (`ui/settings-modal-autofill-lock.md`).

## What does the session cookie carry, and what changes at sign-in?
- `init.php` sets `session.cookie_samesite = Lax` (PHP 7.3+) and `session.use_strict_mode = 1`: an id the browser was not given by this server starts a new session instead of being adopted. The checkout screens (`express_order.php`, `order_preview.php`) re-issue the cookie as `SameSite=None` for a payment gateway's return POST, after `validate_token_field()`, never before.
- `pg_session_sign_in()` renews the form token (`initialize_token()`) with the identity; cart, order keys and a pending redirect carry over.
- Links in e-mails are built from `HOSTNAME_SETTING`, never `HOSTNAME`/`HTTP_HOST` (`reset_password.php`, `import_users.php`, `affiliate_sign_up_form.php`, `add_comment.php`); `pg_signin_redirect_uri()` keeps `HOSTNAME` on purpose (the callback must land on the host the sign-in started on).
- A column named from a device type goes through `sql_device_type()`.

## Is the install screen's unlock form throttled?
- Per name like the sign-in screen (`pg_login_throttle_guard()` before the password, `pg_login_record_failure()` after a miss; 429 past the limit), besides its own per-address file counter (5 in 15 min). It never runs init.php, so `install/index.php` loads `waf.php` itself (no `waf_run()`); before PR #713 `pg_login_throttle_ready()` was false there and nothing counted. Test: tests/install_unlock_throttle_test.php.

Related: docs/memory/core/output-escaping.md, docs/memory/core/session-token-binding.md
