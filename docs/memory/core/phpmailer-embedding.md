---
name: phpmailer-embedding
description: Which PHPMailer the product loads (6.12.0 from includes/phpmailer/src), the unloaded 5.2.22 files beside it, and why both are quiet on PHP 8.5 (#9470)
module: mail
---

## Which PHPMailer runs?
- 6.12.0 (`includes/phpmailer/VERSION`), namespaced, from `includes/phpmailer/src/`. Only `includes/fn/mail.php` loads it (`Exception.php`, `PHPMailer.php`, `SMTP.php`) and `use`s its classes (`functions-php-split-rules.md`).
- Its `composer.json` asks for PHP 5.5 or later, so the 7.1 base holds.

## What are class.phpmailer.php and class.smtp.php?
- PHPMailer 5.2.22 files left in the same folder; no product file loads them, and `includes/.htaccess` denies web requests to the folder. `get_oauth_token.php` is the 6.x helper script, also unused.
- They carry `(boolean)`/`(integer)` casts and unguarded `openssl_pkey_free()` calls, which PHP 8.5 would report if the files were ever compiled. `tests/bundled_library_php85_test.php` fails if any product file names them.

## Is it clean on PHP 8.5?
- Yes, unpatched (checked 2026-11-14). 6.12.0 calls `openssl_pkey_free()` only in DKIM signing (`DKIM_Sign()`), both behind `\PHP_MAJOR_VERSION < 8`, and uses no non-canonical cast. The test above checks both.
- A newer release (7.x was out in November 2026) is not needed for PHP 8.5; moving to it is an update of its own, read for behaviour changes first.
