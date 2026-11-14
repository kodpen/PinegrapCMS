---
name: deprecated-resource-free-calls
description: Handles are freed with unset(), not curl_close()/finfo_close(); a test keeps own code free of what PHP 8.1-8.5 deprecates; db_connect() checks a connection with SELECT 1, not mysqli_ping()
---

## How is a cURL or finfo handle freed?
- `unset($ch)` / `unset($finfo)`. `curl_close()`, `finfo_close()` and `openssl_x509_free()` do nothing since PHP 8.0 (the handles are objects freed with their last reference) and are deprecated in 8.5 (`openssl_x509_free()` in 8.4). On PHP 7 `unset()` drops the last reference to the resource, which frees it as the close call did, so behaviour is the same on 7.1-8.5.
- A call kept on purpose sits behind `if (PHP_VERSION_ID < 80000)` with a `phpcs:ignore` naming the guard on the same line (`imagedestroy()` in `includes/image_engine.php`, `xml_parser_free()` in `includes/workspace/notes_enex.php`). New code just unsets.
- Embedded libraries (`includes/stripe`, `iyzipay-php`, `phpmailer`, `dompdf`, `phpexcel`, `boxpacker`, `qrcode`, root `mysqldump.php`, `pclzip.lib.php`, `excel_reader2.php`) keep their own calls; the test does not read them.

## What keeps a deprecated call out of own code?
- `tests/deprecated_calls_test.php` reads every PHP file under `pinegrap/`, `tools/`, `tests/` and at the repository root by token for what PHP 8.1-8.5 deprecates: the functions (free calls, `utf8_encode()`, `strftime()`, `mysqli_ping()`, `lcg_value()`…).
- Also the constants (`FILTER_SANITIZE_STRING`, `E_STRICT`, `PDO::MYSQL_ATTR_*`…), the casts `(integer)`/`(boolean)`/`(double)`/`(binary)`, the backtick operator, `->setAccessible()`, `get_class()` without an argument and `fputcsv()`/`fgetcsv()`/`str_getcsv()` without the escape argument. A line with `phpcs:ignore` is let through.
- Why: `bash tools/static_analysis.sh` checks only `pinegrap/`, so `tools/ws_client.php` kept two `curl_close()` calls that logged on PHP 8.5 until PR #9468.

## How does db_connect() tell a reused connection is alive?
- It runs `SELECT 1` on `db::$con` inside `try`/`catch (Throwable)` and frees the result. `mysqli_ping()` is deprecated in 8.4, and on a closed connection PHP 8 throws instead of returning `false`; either failure falls through to a new connection.

## What else went with it?
- `CURLOPT_BINARYTRANSFER` (no effect since PHP 5.1.3, deprecated 8.4) is gone from the update download in `includes/panel/software.php`.
- `cloudflare.php` and the root `download_assistant.php` pass `ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401` to `htmlspecialchars()`: PHP 8.1's default, so nothing changes there, and PHP 7 now escapes quotes in the attribute values too.
- `import_design.php` and `install/index.php` followed in #1757, `submit_order.php` in #1831 and `includes/fn/output.php` in #1852; `tools/ws_client.php` unsets its handle since #9468.
