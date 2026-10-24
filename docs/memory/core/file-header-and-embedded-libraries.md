---
name: file-header-and-embedded-libraries
description: The Pinegrap file header every new PHP/JS file must carry, which files it applies to, the PHP 7.1 base rule when embedding a third-party library, and the libraries with a local change.
---

## Which header does a new file carry?
- A file written for Pinegrap does NOT carry the LiveSite header. The "Originally developed as LiveSite..." block belongs only to inherited files (`get_help_url.php`, `add_to_cart.src.js`...), which keep it.
- Every newly written PHP/JS file opens with the header below. Today that covers: everything under `includes/` (third-party libraries excluded), `waf.php` / `waf_ranges_job.php` / `view_waf_log.php`, `manifest.php` / `manifest_icon.php` / `sw.js`, the file manager (`view_folders.php`, `view_folder_and_files.php`, `view_folder_and_files_f.php`), and the external API (`integration.php`, `api_settings.php`, `api_docs.php`, `api_sync_job.php`, `api_webhook_job.php`, `marketplace_settings.php`).
```php
<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * (the file's own description, if any)
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
```
- Copy it when creating a file; spell it `Pinegrap`, not `PineGrap`.
- Never touch the headers of third-party libraries: `includes/phpmailer`, `stripe`, `iyzipay-php`, `phpexcel`, `boxpacker`, `dompdf`, `qrcode`.

## What PHP base must an embedded library respect?
- Before putting a library under `includes/`, check the `php` constraint in its `composer.json` and PIN the version accordingly: the base must stay at 7.1 or below.
- Raising the PHP base is the owner's (Erdal's) decision, never a side effect of a library choice. Example: html5-php 2.11 needs PHP 7.4, so it is pinned at 2.10.1.
- The exception: `includes/iyzipay-php` (2.0.59) asks for PHP 7.4 and is patched to load on 7.1 instead; an update re-applies the patch (commerce/iyzipay-sdk-php71-patch.md).

## Which embedded libraries carry a local change?
- `includes/iyzipay-php`, to load on PHP 7.1 (above).
- `mysqldump.php` (ifsnop/mysqldump-php at the software root; the backup, `includes/fn/backup.php`): its constructor sets the buffered-query option through `Pdo\Mysql::ATTR_USE_BUFFERED_QUERY` when that class exists (PHP 8.4 and later; the same value, 1000), because `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY` is deprecated from 8.5 (#?). On 8.5 the unit tests stopped with an ERROR there. An update re-applies it.

Related: docs/memory/core/dompdf-embedding.md
