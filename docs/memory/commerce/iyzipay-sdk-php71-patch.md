---
name: iyzipay-sdk-php71-patch
description: includes/iyzipay-php (iyzico SDK 2.0.59, asks for PHP 7.4) patched for 7.1: typed properties dropped, \stdClass in the refund mapper; why, what is left, what an SDK update re-applies
---

## Why is the bundled SDK patched?
- Its `composer.json` asks for PHP 7.4; the product runs on 7.1 and up. The SDK's autoloader `require`s a class file when the class is first used.
- PHP 7.1-7.3: `AmountBaseRefundRequest` and `AmountBaseRefund` (the refund of both iyzico drivers) did not parse (typed properties). `pg_payment_call()` turned the ParseError into "The payment service could not be reached.": nothing was sent, and no refund from the order screen or a return's refund ever went through.
- PHP 7.1 only: an `object` parameter type is read as a class named `Iyzipay\Model\Mapper\object`. With the property types dropped alone, the refund would leave and every answer, success included, would read as "an answer that cannot be read".
- CI never saw it: it ran PHP 8.3 only, and `tools/lint.php` and `tools/phpcs.xml` skip the folder. Its PHP 7.1 job now checks the folder (`tools/lint.php --vendored`, workflow/ci-workflows.md).

## What was changed? (#1353)
- 24 files under `src/Iyzipay/Request/` and `src/Iyzipay/Model/` (all typed properties of the SDK): `private string $x;` became `private $x;`, defaults kept, nothing else. A getter that reads a property never set throws TypeError (null against its return type) instead of Error; the `?? null` getters still give null. Nothing tells the two apart: `pg_payment_call()` takes both as `\Error`, and the refund driver's TypeError catch wraps only `create()`, after it set every field of the request.
- `Model/Mapper/AmountBaseRefundMapper.php`: `object $jsonObject` became `\stdClass $jsonObject`. json_decode() gives a \stdClass for every JSON object; any other body is still the TypeError the refund driver reads as unreadable (payment-unclear-answers.md). Only the log text changed (stdClass for object).

## What was left as shipped?
- Classes the product never loads: the `object` parameter of 7 other mappers (subscription list and customer delete, plus installment payment and resource, C2C sub-merchant, scroll transactions, Iyzilink product search) and the covariant return type of `PlusInstallmentPaymentMapper::create()` (fatal on 7.1-7.3 when loaded). Patch them the same way before a driver uses them.
- PHP 8.4/8.5 deprecations (implicitly nullable parameters in `IyzipayResource`, `JsonBuilder`, `RequestStringBuilder`, `IyziAuthV2Generator`; `curl_close()` in `Curl.php`): hidden by init.php's error_reporting, let through by the test runner.

## What does an SDK update need?
- Both changes again; `test_payment_iyzico_sdk_keeps_to_php_7_1` (tests/payment_iyzico_test.php) fails until they are in. Then on PHP 7.1: `php -l` on every file, phpcs with PHPCompatibility and `--runtime-set testVersion 7.1-` on the folder (tools/static_analysis.sh fetches both), and every class the drivers use loaded once.

## How was it verified?
- PHP 7.1, 7.2, 7.3, 7.4, 8.3 and 8.5: the refund's request body and the mapped answer (success, refusal, cURL body, `{}`, HTML page, empty, `null`, array, number) are the same on all; the payment tests pass on 7.1, 7.2, 7.3, 8.3 and 8.5. No iyzico sandbox was used.

Related: docs/memory/commerce/payment-driver-iyzico.md, docs/memory/commerce/payment-unclear-answers.md, docs/memory/core/file-header-and-embedded-libraries.md
