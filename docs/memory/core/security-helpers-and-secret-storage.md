---
name: security-helpers-and-secret-storage
description: CSRF/escape helpers (get_token_field, validate_token_field, h) and how secret config fields are stored encrypted as one blob, never exposed as constants.
---

## Which security helpers exist?
- `get_token_field()` outputs the CSRF token input. `validate_token_field()` validates the token on POST. `h($val)` is HTML escape (htmlspecialchars).

## Does lang() escape its {var:N} values?
- No. `lang(array('string' => '... {var:1}', 'vars' => $x))` inserts `$x` as is, and `liveform` prints notices, warnings and errors as raw HTML. A stored name or URL that goes into a notice must be wrapped: `'vars' => h($name)`. Symptom fixed in #541: an anonymous OAuth dynamic registration (`pg_oauth_register()`) could plant `<img onerror>` in `client_name`, which ran in the panel when a manager removed or re-enabled the client on `api_settings.php`; the same shape covered device names and webhook URLs there and the ended connection on `connected_apps.php`.

## Is a form field's label HTML?
- No. `fields.label` is the plain text the field editor's `<input>` accepted (a role-3 user with folder edit rights writes it); only an information field's `information` column is rich text. The panel screens (`add_submitted_form.php`, `edit_submitted_form.php`, `view_fields.php`) and the validation messages (`custom_form.php`, `shopping_cart.php`, `express_order.php`, both submitted-form screens) print it through `h()`. The classic front-end renderers (`includes/fn/content.php`, `get_form_item_view.php`, `get_custom_form_confirmation_screen_content.php`) still print it raw pending the owner's decision in `plans/security-findings-2026-10.md`; the widget renderer escapes it.

## How are secret-bearing config fields stored?
- A secret config field is NEVER opened into a constant. Paraşüt's secret key and password live in `config.parasut_credentials_enc` as ONE encrypted JSON blob (`"<ciphertext>:<iv>"`, the pair returned by `encrypt_string_with_iv()`); `init.php` only takes the blob into a constant.
- Decryption happens in the request that actually calls Paraşüt, via `_parasut_credentials()`.
- The same pattern is used in `mp_credentials_encode()` / `mp_credentials_decode()` (`includes/api/outbound/connectors/base.php`): a single column, so a new provider field needs no schema change.
- The settings screen does NOT render the value back; an empty box means "keep the saved value".
- `api_settings.php` does not encrypt: it hashes with `hash_hmac`. That pattern cannot be used for a credential that must be read back.

## What does the session cookie carry, and what changes at sign-in?
- `init.php` sets `session.cookie_samesite = Lax` (PHP 7.3+) and `session.use_strict_mode = 1`: an id the browser was not given by this server starts a new session instead of being adopted. The checkout screens (`express_order.php`, `order_preview.php`) re-issue the cookie as `SameSite=None` for a payment gateway's return POST, after `validate_token_field()`, never before.
- `pg_session_sign_in()` renews the form token (`initialize_token()`) with the identity; cart, order keys and a pending redirect carry over.
- Links in e-mails are built from `HOSTNAME_SETTING`, never `HOSTNAME`/`HTTP_HOST` (`reset_password.php`, `import_users.php`, `affiliate_sign_up_form.php`, `add_comment.php`); `pg_signin_redirect_uri()` keeps `HOSTNAME` on purpose (the callback must land on the host the sign-in started on).
- A column named from a device type goes through `sql_device_type()`.

## Is the install screen's unlock form throttled?
- Per name like the sign-in screen (`pg_login_throttle_guard()` before the password, `pg_login_record_failure()` after a miss; 429 past the limit), besides its own per-address file counter (5 in 15 min). It never runs init.php, so `install/index.php` loads `waf.php` itself (no `waf_run()`); before PR #713 `pg_login_throttle_ready()` was false there and nothing counted. Test: tests/install_unlock_throttle_test.php.

Related: docs/memory/core/file-integrity-check.md
