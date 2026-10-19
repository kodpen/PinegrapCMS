---
name: php-compatibility-and-bugs
description: Open items from the 2026-10-19 static analysis (PHPStan level 0, PHPCompatibility): undefined variables, duplicate keys, nested functions, PHP 8.4/8.5 deprecations, dead extension remnants, CI steps
---

## Where does this come from?
- `php -l`, PHPStan 2.3 level 0 and PHPCompatibility (`testVersion 7.1-`) over the product tree, vendor libraries excluded; verified in the source, not on a running site. Architecture refactors: `refactor-candidates.md`. Done: the `url_to_absolute()` PCRE2 failure (`designer/import-zip-url-resolution.md`); the undefined folder list in `select_files_for_theme_designer()` (#615); undefined `$output` in `get_update_address_book.php` and `$crc`/`$c_len`/`$unc_len` in the `view_orders.php` zip `add_dir()` (#616); the duplicate `delete_folder_title` key in `view_folders.php` (#617); whitespace after the closing `?>` of the root `index.php`, `includes/templates/pagination.php` and `software_update.php` (#618).

## Which bugs are confirmed? (one PR each)
1. `get_catalog.php:556-568`: `$output_featured_items`, `$output_image`, `$output_short_description`, `$output_price` used before assignment in the "top items" branch; legacy custom style layer, ask before touching.
2. Duplicate array key: `includes/fn/files.php:199` (`'å'` twice, the second mapping is dead; which letter was meant?).
3. Named functions declared inside functions or branches (second call = "cannot redeclare"; blind spot for static analysis): `includes/dashboard/widgets/widget_14.php:148`, `includes/fn/mail.php` 39-80, `includes/panel/explorer.php` 260-332, `router.php` 904/911. Convert to closures or guard with `function_exists()`.

## Which deprecations matter for "PHP 7.1-8.5"?
- PHP 8.5 deprecated: `curl_close()` 50 calls (`submit_order.php` 21), `imagedestroy()` 25 (`edit_file.php` 13, `includes/fn/image.php` 7), `finfo_close()` 6; PHP 8.4: `mysqli_ping()` `core.php:77`, `openssl_x509_free()` `update.php:553`, `CURLOPT_BINARYTRANSFER` `includes/panel/software.php:499`, `fgetcsv()`/`fputcsv()` without the `$escape` argument in 26 calls (the six `import_*.php`, `includes/translate/jobs.php`, `erp/audit.php`, `erp/bank_import.php`, `add_theme_file.php`, `view_visitor_report.php`). PHP 8.1: `auto_detect_line_endings` in the six importers; `htmlentities()`/`html_entity_decode()` default flags changed (`cloudflare.php` 23 calls, `download_assistant.php` 3) - pass the flags explicitly.
- Dead remnants to remove once the owner agrees: `mysql_*` behind `DB_LEGACY` (`core.php` 168-180, `router.php` 160-170; gone since PHP 7.0), `pfpro_process()` (`submit_order.php` 2325/5079, extension removed in PHP 5.1), `get_magic_quotes_gpc()` (`init.php:847`, `install/index.php:357`). `mcrypt` (`ecommerce.php` 727-739) is a guarded PHP <= 7.1 path: keep while 7.1 is supported.
- PHP 7.2+ APIs (`JSON_INVALID_UTF8_SUBSTITUTE`, `PHP_OS_FAMILY`, `imagebmp`, `imagecreatefrombmp`) are all guarded; 7.1 compatibility holds. `custom_form.php:1793` "break outside loop" is a false positive.

## How would CI keep this clean?
- Two phar-based steps in `php-checks.yml` (no Composer in the product): PHPStan level 0 with `Constant X not found` ignored and vendor paths excluded (this catches items 1-3), and `phpcs --standard=PHPCompatibility --runtime-set testVersion 7.1-` with the `trim()` form-feed sniff excluded (2,900 hits, PHP 8.6 only).
