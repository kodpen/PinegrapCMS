---
name: php-compatibility-and-bugs
description: Open items of the 2026-10-19 static analysis: undefined variables, a duplicate key, leftover deprecations, dead remnants; done: #972, #1039, #1450, #1669, #1744, #1757
---

## Where does this come from?
- `php -l`, PHPStan 2.3 level 0 and PHPCompatibility (`testVersion 7.1-`) over the product tree, vendor libraries excluded; verified in the source, not on a running site. Architecture refactors: `refactor-candidates.md`. Done: the `url_to_absolute()` PCRE2 failure (`designer/import-zip-url-resolution.md`); the undefined folder list in `select_files_for_theme_designer()` (#615); undefined `$output` in `get_update_address_book.php` and `$crc`/`$c_len`/`$unc_len` in the `view_orders.php` zip `add_dir()` (#616); the duplicate `delete_folder_title` key in `view_folders.php` (#617); whitespace after the closing `?>` of the root `index.php`, `includes/templates/pagination.php` and `software_update.php` (#618).

## Which bugs are confirmed? (one PR each)
1. `get_catalog.php:556-568`: `$output_featured_items`, `$output_image`, `$output_short_description`, `$output_price` used before assignment in the "top items" branch; legacy custom style layer, ask before touching.
2. Duplicate array key: `includes/fn/files.php:199` (`'å'` twice, the second mapping is dead; which letter was meant?).
3. Done (#972): the named functions declared inside functions are closures or plain values; the PHPStan baseline holds none (`code-audit-2026-10-followups.md` item 2).
4. Done (#1039): the unguarded `imagecreatefrombmp()` of `edit_file.php`'s WebP conversion is gone with the screen's own GD code; its Rotate and WebP buttons go through `pg_process_image_file()` (`platform/image-processing-engine.md`).

## Which deprecations matter for "PHP 7.1-8.5"?
- Done (#1744, `core/deprecated-resource-free-calls.md`): `curl_close()`/`finfo_close()` outside the files below, `mysqli_ping()` (`core.php`), `openssl_x509_free()` (`update.php`), `CURLOPT_BINARYTRANSFER` (`software.php`), the `htmlspecialchars()` flags of `cloudflare.php` and `download_assistant.php`. Earlier: `imagedestroy()` (#1669), the CSV `$escape` argument (#1450).
- Done (#1757): `auto_detect_line_endings` in the six `import_*.php` (`core/csv-line-endings.md`); `curl_close()` in `import_design.php` and `install/index.php`.
- Done (#1831, #1852): `curl_close()` in `submit_order.php` (17) and `includes/fn/output.php` (1); none is left in the product tree.
- Dead remnants to remove once the owner agrees: `mysql_*` behind `DB_LEGACY` (`core.php` 168-180, `router.php` 160-170; gone since PHP 7.0), `pfpro_process()` (`submit_order.php` 2325/5079, extension removed in PHP 5.1), `get_magic_quotes_gpc()` (`init.php:847`, `install/index.php:357`). `mcrypt` (`ecommerce.php` 727-739) is a guarded PHP <= 7.1 path: keep while 7.1 is supported.
- PHP 7.2+ APIs (`JSON_INVALID_UTF8_SUBSTITUTE`, `PHP_OS_FAMILY`, `imagebmp`, `imagecreatefrombmp`, `imageavif`, the `idn_to_ascii()` variant) are guarded everywhere. `custom_form.php:1793` "break outside loop" is a false positive (a brace-less `foreach`).

## How does CI keep this clean?
- `bash tools/static_analysis.sh` runs PHPStan level 0 and PHPCompatibility (`testVersion 7.1-`) in `php-checks.yml` (PR #673) against baselines that hold every item above, so new code cannot add one: docs/memory/workflow/static-analysis.md. The PR that fixes an item regenerates the baselines in the same change, so they shrink.
