---
name: webauthn-verifier
description: WebAuthn (passkey) verifier in includes/webauthn/ (PR #594): API, what it accepts and refuses, error codes, record fields, W3C test vectors, what a caller must do
---

## What is it?
- A relying-party verifier with no Composer: `webauthn.php` (options, browser JSON, `pg_webauthn_verify_registration()`, `pg_webauthn_verify_assertion()`), `cbor.php` (strict CBOR, base64url), `cose.php` (COSE key to PEM, signatures, packed certificate rules). Loaded on demand: `require_once(PG_FUNCTIONS_DIR . '/includes/webauthn/webauthn.php')`; gate `PG_FUNCTIONS_DIR`. Follows W3C WebAuthn Level 3, sections 7.1 and 7.2.
- Inputs are raw bytes; `pg_webauthn_registration_response()` / `pg_webauthn_assertion_response()` decode the JSON `PublicKeyCredential.toJSON()` posts. Answers: `array('ok' => true, ...)` or `array('ok' => false, 'error' => <code>)`; the codes are listed in the `webauthn.php` header.

## What does it accept?
- Credentials ES256 (P-256) and RS256 (2048-8192 bit). Attestation formats `none` and `packed` (self, or x5c checked for signature and the 8.2.1 certificate rules); TPM, Android, Apple, U2F statements are refused with `attestation_format`. Attestation is never trusted (no roots): x5c is reported as `basic`, unverified. Options ask for attestation `none`.
- Refused always: `crossOrigin` other than false, any `topOrigin`, a counter that does not move forward (unless both are 0), attested data in a sign-in.

## Why is the CBOR reader so strict?
- Each CBOR type keeps its own PHP shape: bytes `PgWebAuthnBytes`, arrays `PgWebAuthnList`, maps PHP arrays. With lists as plain arrays a map `{1: cert}` given as `x5c` passed the list check and raised PHP warnings. Text keys PHP would turn into integers, duplicate keys, tags, indefinite lengths, depth over 16 and more than 64 entries per container are refused (colliding integer keys share one hash bucket).
- Size caps: client data 16 KB, attestation object 16 KB, authenticator data 4 KB, response JSON 64 KB.

## What must a caller pass?
- `expected`: challenge (at least 16 bytes; empty after an expired session gives `challenge`), origin(s), rp_id. `user_verification`: only `preferred`/`discouraged` relax it, anything else set means required.
- The stored record: id, PEM, alg, sign_count, backup_eligible (`'0'`/`'1'` from the database are read; anything unreadable refuses), user_handle of the account that owns the record. A missing id, counter or alg refuses; nothing is skipped because it was left out.

## What is left to the caller?
- RP ID and origin from `URL_SCHEME` + `HOSTNAME_SETTING` (`pg_webauthn_rp_id()`, `pg_webauthn_origin()`), never the request Host. No passkeys on an IP address or plain http (except localhost): `pg_webauthn_secure_context()`.
- A Unicode host name goes to ASCII as the browser's URL parser writes it: `idn_to_ascii()` UTS #46 non-transitional with bidi and joiner checks (`straße.de` → `xn--strae-oqa.de`, `ςfoo.gr` → `xn--foo-lzc.gr`). Before PR #1105 (issue #772) transitional processing gave `strasse.de` (another domain): the browser refused the RP ID and the signed origin never matched. Turkish letters convert the same either way.
- Take the challenge out of the session before verifying (one use). Refuse a credential ID already registered (7.1 step 26). Show the visitor one plain refusal; the code goes to the log only.

## How is it tested?
- `tests/webauthn_test.php`: RFC 8949 examples, all W3C Level 3 test vectors (section 16), fixed self-signed certificates for each certificate rule, refusals made by changing one part of a response signed with a W3C test key, and 1,500 deterministic mutations (no warning, no changed sign-in accepted).
