---
name: openssl-key-generation
description: Why openssl_pkey_new()/openssl_pkey_export() fail without an OpenSSL config file; pg_openssl_key_new() retries with includes/openssl.cnf; DKIM keeps the old pair; Apple sign-in needs none.
---

## Why does key generation fail although openssl functions and curves exist?
- `openssl_pkey_new()` (and `openssl_pkey_export()`) read an OpenSSL configuration file before generating a key. On Windows it does not ship with the install; on shared hosting `OPENSSL_CONF` may point to a non-existent path.
- The call then fails with `error:07000072:configuration file routines::no such file`; correct arguments do not save it.
- Symptom: a key-generating feature says "unavailable" while the functions and the curve list are present.

## What is the rule for any code path that generates keys?
- Make the key with `pg_openssl_key_new($arguments)` (`includes/fn/core.php`), never `openssl_pkey_new()` directly: it tries normally, then once more with `'config' => includes/openssl.cnf`, and returns `array('key', 'arguments')` or false. Pass those `arguments` to `openssl_pkey_export()`: on a server without a config file the export fails without it even when the key exists.
- Users: the VAPID key of `includes/push.php` (`pg_push_create_vapid_keys()`) and the DKIM pair (`pg_dkim_key_pair()` in `includes/fn/mail.php`). `tests/dkim_key_test.php` checks both and that neither calls `openssl_pkey_new()` itself.
- That file ships with the product; it holds nothing secret or site-specific.
- **Do not write a providers section** into it: if a section is written, only the listed providers are loaded.

## Does Apple sign-in need the file?
- No: `includes/fn/signin.php` only reads the pasted .p8 key (`openssl_pkey_get_private()`) and signs with it, which works without one (Windows PHP 8.5.11, OpenSSL 3.5.8, no `openssl.cnf`). Only making a key and `openssl_pkey_export()` (pass it the same `config`) need it; the tests' `signin_test_pkey_new()` retries with the shipped file (#1678).

## Which paths do not follow it yet?
- None in the product. The tests keep their own copy (`signin_test_pkey_new()`).

## What does the DKIM screen do when no key can be made?
- `smtp_settings.php` makes the pair first (`pg_dkim_key_pair()`, RSA 2048) and deletes the old `dkim.key` / `dkim.pub` files and records only once it exists. When OpenSSL makes none it warns "The DKIM key could not be generated on this server…" and the old pair keeps signing.
- Symptom fixed in #1696: it deleted first and called `openssl_pkey_new()` and the export without the config, so a Windows/IIS site lost its pair, nothing was written and nothing was shown.

## Which PEM form does openssl_pkey_export() write for an EC key?
- SEC1 (`BEGIN EC PRIVATE KEY`) on PHP 7.1-8.0, PKCS#8 (`BEGIN PRIVATE KEY`) from 8.1 on (PHP 8.1 UPGRADING); RSA is PKCS#8 everywhere. A stored key is read back as stored, header included (the VAPID key of `includes/push.php`), so either form keeps working after a PHP upgrade.
- `pg_signin_pem_private_key()` puts any pasted body under the PKCS#8 label, right for Apple's .p8 file; a SEC1 body there cannot be read. A test that needs Apple's form builds the DER itself (`signin_test_ec_key()` in `tests/signin_test.php`); it used `openssl_pkey_export()` and failed on PHP 7.1-8.0 (#1357).

## How to debug it?
- Loop `openssl_error_string()` to get the real reason. A call silenced with `@` only says "false".
