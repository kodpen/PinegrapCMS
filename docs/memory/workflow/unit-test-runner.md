---
name: unit-test-runner
description: tools/test.php: what it loads, which PHP errors fail a test and which are let through (vendored deprecations), and what a run on another PHP version needs
---

## What does tools/test.php load?
- `pinegrap/functions.php` (every module under `includes/fn/`) and the ERP `money.php`, no database. Each `tests/*_test.php` is required in name order and its `test_*` functions run; with a filter only matching files and functions run, but every test file is still loaded.

## Which PHP errors fail a test?
- The error handler turns every warning, notice and deprecation into an `ErrorException`, so the test is an ERROR instead of printing and passing.
- Not a deprecation raised inside a vendored library (the folders `tools/lint.php` skips: `includes/phpmailer`, `stripe`, `iyzipay-php`, `phpexcel`, `boxpacker`, `dompdf`, `assets/lib`): it is let through. Symptom fixed: on PHP 8.4 and later PHPExcel's implicitly nullable parameters (`PHPExcel.php:806` first) stopped the whole run with a fatal error at the first test file that loads it, before any test ran (#1283).

## What does a run on another PHP version need?
- `-d memory_limit=-1`: the official Docker images default to 128M and the full suite needs more (it ran out in `designer_blocks_test.php` on 7.1 and in `office_dom_ceiling_test.php` on 8.5). Also the extensions some tests use: `intl` (short link names fold "Ürün" through `Normalizer`), `zip` (office tests), `pdo_mysql` (the mysqldump settings test). Without them those tests fail for the missing extension, not for the PHP version.
- CI runs PHP 8.3 only (docs/memory/workflow/ci-workflows.md).
