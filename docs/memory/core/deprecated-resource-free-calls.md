---
name: deprecated-resource-free-calls
description: Handles are freed with unset(), not curl_close()/finfo_close()/openssl_x509_free(); db_connect() checks a reused connection with SELECT 1, not mysqli_ping() (PR #1744)
---

## How is a cURL or finfo handle freed?
- `unset($ch)` / `unset($finfo)`. `curl_close()`, `finfo_close()` and `openssl_x509_free()` do nothing since PHP 8.0 (the handles are objects freed with their last reference) and are deprecated in 8.5 (`openssl_x509_free()` in 8.4). On PHP 7 `unset()` drops the last reference to the resource, which frees it as the close call did, so behaviour is the same on 7.1-8.5.
- A call kept on purpose sits behind `if (PHP_VERSION_ID < 80000)` with a `phpcs:ignore` naming the guard (`captcha.php`, `auth.php`). New code just unsets.
- Vendored libraries (`includes/stripe`, `includes/iyzipay-php`, `includes/dompdf`) keep their own calls; they are not analysed.

## How does db_connect() tell a reused connection is alive?
- It runs `SELECT 1` on `db::$con` inside `try`/`catch (Throwable)` and frees the result. `mysqli_ping()` is deprecated in 8.4, and on a closed connection PHP 8 throws instead of returning `false`; either failure falls through to a new connection.

## What else went with it?
- `CURLOPT_BINARYTRANSFER` (no effect since PHP 5.1.3, deprecated 8.4) is gone from the update download in `includes/panel/software.php`.
- `cloudflare.php` and the root `download_assistant.php` pass `ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401` to `htmlspecialchars()`: PHP 8.1's default, so nothing changes there, and PHP 7 now escapes quotes in the attribute values too.
- `import_design.php` and `install/index.php` followed in #1757. What is left (files other PRs held): `plans/php-compatibility-and-bugs.md`.
