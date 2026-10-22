---
name: openssl-key-generation
description: Why openssl_pkey_new()/openssl_pkey_export() can fail without an OpenSSL config file and the retry rule with includes/openssl.cnf (no providers section).
---

## Why does key generation fail although openssl functions and curves exist?
- `openssl_pkey_new()` (and `openssl_pkey_export()`) read an OpenSSL configuration file before generating a key. On Windows it does not ship with the install; on shared hosting `OPENSSL_CONF` may point to a non-existent path.
- The call then fails with `error:07000072:configuration file routines::no such file`; correct arguments do not save it.
- Symptom: a key-generating feature says "unavailable" while the functions and the curve list are present.

## What is the rule for any code path that generates keys?
- Try normally first; on failure retry once with `'config' => includes/openssl.cnf` (example: `includes/push.php` `pg_push_create_vapid_keys()`).
- That file ships with the product; it holds nothing secret or site-specific.
- **Do not write a providers section** into it: if a section is written, only the listed providers are loaded.

## Which paths do not follow it yet?
- The DKIM pair in `smtp_settings.php` calls `openssl_pkey_new()` once, after it has already deleted the old `dkim.key` / `dkim.pub` files and records. On a server without a config file it removes the pair, writes nothing and shows no message.

## How to debug it?
- Loop `openssl_error_string()` to get the real reason. A call silenced with `@` only says "false".
