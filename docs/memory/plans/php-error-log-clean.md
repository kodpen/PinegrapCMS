---
name: php-error-log-clean
description: Plan (owner 2026-11-14): the PHP error log of the owner's dev machine read for Pinegrap's own deprecations and warnings on PHP 8.5, and their causes fixed; steps EL1 to EL6
---

## What is asked?
- Owner (2026-11-14, in the architect's window): "yerelde error.log dosyasında depricated lerde var onlar için bir işçi başlatabilirim sen plan hazırlarsan". The worker is a local one: the log is on his machine only, at the root of the main checkout (`error.log`, about 200 MB, 15 October to 13 November 2026, PHP 8.5).

## What does the log hold? (counted 2026-11-14)
- 142,423 "PHP Deprecated" lines, and all but 37 are not Pinegrap's: PHP's one log on that machine also takes other projects (paths outside the checkout, a phpMyAdmin copy). Lines are read by their path, never by their number.
- Pinegrap's 37: `$http_response_header` in `includes/fn/system_status.php` and `import_design.php`, last seen 20 October and gone since (`tests/http_response_header_test.php` holds it); `curl_close()` in `tools/ws_client.php`, still there.
- Bundled libraries still call what PHP 8.5 deprecates and will log when their path runs: `includes/iyzipay-php` and `includes/stripe` (`curl_close()`), `includes/phpmailer` (`openssl_pkey_free()`), `includes/dompdf` (`imagedestroy()`, `xml_parser_free()`, `curl_close()`, `$http_response_header`).
- The real noise is warnings: since 1 November 8,844 warning, fatal and parse lines from the main checkout in 719 shapes. The first files: `duplicate_products.php` (about 5,000, array offset on null), `view_visitor_report.php`, `includes/workspace/access.php`, `includes/fn/order_report.php`, `edit_form_list_view.php`, `get_file.php` ("Constant USER_… already defined, this will be an error in PHP 9"), `view_order.php` (undefined variable), `includes/designer_ai.php` (array to string).
- Line numbers are those of the checkout on that day; a line may be fixed since.

## Which steps?
- EL1 (S, no product code) Census. Every distinct Pinegrap line of the log (main checkout only, worktrees and scratch folders left out), grouped by file, checked against `development`: still there or gone. The list goes into `plans/php-error-log-clean-census.md`, most frequent first. A fatal error that is a real fault gets a `bug` issue.
- EL2 (S) Own code. Done #9468. `tools/ws_client.php` frees its handle without `curl_close()` (`core/deprecated-resource-free-calls.md`). A test like `http_response_header_test.php` scans own code for the calls PHP 8.1 to 8.5 deprecate; a guarded call keeps its `phpcs:ignore`.
- EL3 (M) Bundled libraries, one PR each: the library's newer release that is clean on 8.5 and still runs on PHP 7.1, or the smallest guarded patch written down in `core/`. A release that changes behaviour is asked first.
- EL4 (M) The eight files above: the cause fixed (a missing row, a key that may be absent, a constant defined twice), never an `@`; a test for each pure function touched.
- EL5 (M, as many PRs as the census needs) The rest of the census by family, each PR of the usual size.
- EL6 (S) Close-out: what was learnt into `core/error-reporting.md`, this plan and its census deleted.

## What does every step keep?
- No behaviour changes: a warning's cause is fixed so that the screen answers as before. A fix that would change what a screen shows is asked first.
- PHP 7.1 to 8.5 all run the result (`bash tools/static_analysis.sh`).
- The log itself never enters the repository: it holds addresses and request data.
- EL1 first; EL2 and EL3 may run beside it; EL4 and EL5 after EL1. Claim as `[plan:php-error-log-clean] <step> — <topic>`.