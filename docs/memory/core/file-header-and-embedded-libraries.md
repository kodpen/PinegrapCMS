---
name: file-header-and-embedded-libraries
description: The Pinegrap file header every new PHP/JS file must carry, which files it applies to, and the PHP 7.1 base rule when embedding a third-party library.
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

Related: docs/memory/core/dompdf-embedding.md
