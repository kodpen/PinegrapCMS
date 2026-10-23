---
name: unit-test-runner
description: tools/test.php: what it loads, which PHP errors fail a test and which are let through (vendored deprecations), what a run on another PHP version needs, and how a test runs editor JavaScript under Node
---

## What does tools/test.php load?
- `pinegrap/functions.php` (every module under `includes/fn/`) and the ERP `money.php`, no database. Each `tests/*_test.php` is required in name order and its `test_*` functions run; with a filter only matching files and functions run, but every test file is still loaded.

## Which PHP errors fail a test?
- The error handler turns every warning, notice and deprecation into an `ErrorException`, so the test is an ERROR instead of printing and passing.
- Not a deprecation raised inside a vendored library (the folders `tools/lint.php` skips: `includes/phpmailer`, `stripe`, `iyzipay-php`, `phpexcel`, `boxpacker`, `dompdf`, `assets/lib`): it is let through. Symptom fixed: on PHP 8.4 and later PHPExcel's implicitly nullable parameters (`PHPExcel.php:806` first) stopped the whole run with a fatal error at the first test file that loads it, before any test ran (#1283).

## What does a run on another PHP version need?
- `-d memory_limit=-1`: the official Docker images default to 128M and the full suite needs more (it ran out in `designer_blocks_test.php` on 7.1 and in `office_dom_ceiling_test.php` on 8.5). Also the extensions some tests use: `intl` (short link names fold "Ürün" through `Normalizer`), `zip` (office tests), `pdo_mysql` (the mysqldump settings test). Without them those tests fail for the missing extension, not for the PHP version.
- CI runs the unit tests on PHP 8.3 and 7.1 (docs/memory/workflow/ci-workflows.md). A run without `gd` skips the image tests and hides their failures: on 7.1 one read WebP with `imagecreatefromstring()`, which reads it only from PHP 7.3.

## How does a test run editor JavaScript under Node?
- It cuts functions out of the source (`style_designer.js`), appends its own steps that `console.log(JSON.stringify(...))`, runs the file with `node` and decodes the output. Skip (return) only when `node --version` fails; output that is not JSON must fail with the output shown, never be treated as a skip.
- Start the appended steps on a new line: a cut ends wherever the next declaration begins, often inside the comment above it, and steps glued to that line are commented out. `tests/designer_page_scripts_test.php` passed this way on any implementation until the newline and the JSON check were added (#1311); its `_sdPreviewDocHtml()` test stubs the editor around the function and counts the Bootstrap bundles.
