---
name: iyzipay-sdk-php85-patch
description: includes/iyzipay-php 2.0.59 kept quiet on PHP 8.4/8.5: nullable parameters marked, curl_close() guarded; why no update, what an update re-applies (#PRNUM)
---

## Why a patch and not a newer SDK?
- The newest release (2.0.64, November 2026) still asks for PHP 7.4, so it would need the 7.1 patch again (`iyzipay-sdk-php71-patch.md`) and be read for behaviour changes; the deprecations are fixed in place on 2.0.59 instead.
- Implicitly nullable parameters (`Type $x = null` without `?`) are reported by PHP 8.4 when the file is compiled, so every iyzico call logged them; `curl_close()` is deprecated in 8.5.

## What was changed?
- `?array $array = null` in `JsonBuilder::addArray()` and `RequestStringBuilder::appendArray()`; `?Request $request = null` in `IyziAuthV2Generator::generateAuthContent()`, `getHmacSHA256Signature()` and `getPayload()`. The `?` is PHP 7.1 syntax.
- `IyzipayResource::getHttpHeadersV2()`, `getHttpHeadersIsV2()` and `prepareAuthorizationStringV2()` take `?Request $request` with no default: the default stood before the required `$options`, so PHP already treated the parameter as required, and `?Request $request = null` there is itself reported from PHP 8.3.
- `Curl.php` calls `curl_close()` only behind `PHP_VERSION_ID < 80000`. The file was already Pinegrap's own: on a cURL failure it writes the error to the PHP log and answers a `failure` JSON instead of an empty body.
- Nothing else; every caller passes the same values as before.

## How is it held?
- `tests/bundled_library_php85_test.php` scans the SDK's `src/` (with dompdf and PHPMailer) for unguarded free calls, implicitly nullable parameters and non-canonical casts, and checks by reflection that each changed parameter still takes null and keeps whether it is optional.
- An SDK update re-applies all of the above, `Curl.php`'s failure answer included; the test fails until it is in.

Related: docs/memory/commerce/iyzipay-sdk-php71-patch.md, docs/memory/core/file-header-and-embedded-libraries.md
