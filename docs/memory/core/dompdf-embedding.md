---
name: dompdf-embedding
description: How dompdf and its chain are embedded in includes/dompdf/: versions, custom autoloader, runtime write location, HTML/charset handling for PDFs.
---

## What is embedded and in what versions?
- `includes/dompdf/`: dompdf 3.1.6 and its chain: php-font-lib 1.0.2, php-svg-lib 1.0.2, html5-php 2.10.1 (pinned, see PHP base rule), PHP-CSS-Parser 8.9.0.
- Layout follows dompdf's release archive, under `vendor/`. The loader is Pinegrap-written `includes/dompdf/autoload.inc.php` (PSR-4 map, `PG_API_ENTRY` gate, no Composer).
- `tools/lint.php` skips the folder. Size ~11 MB, of which 7.6 MB is DejaVu fonts.

## Is it clean on PHP 8.5?
- Yes, unpatched (checked 2026-11-14, #PRNUM). 3.1.6 is the newest release; every `imagedestroy()`, `xml_parser_free()` and `curl_close()` in dompdf, php-svg-lib and Cpdf sits behind `PHP_MAJOR_VERSION < 8`.
- `$http_response_header` is written before it is read in each method (`Helpers::getFileContent()` sets it to `null` first; the callers take it from that method's return), which PHP 8.5 accepts.
- `tests/bundled_library_php85_test.php` checks the guards, canonical casts and that write-first order; an update that breaks one fails it.

## Where may it write at runtime?
- Everything it writes (`fontCache`, `tempDir`) goes under `data/cache/dompdf/`, NEVER into `includes/` — `includes/` is part of the integrity hash.
- Same reason applies to any embedded library: one that writes into `includes/` at runtime shows as "tampered" every hour.

## Why does erp_invoice_pdf() strip comments and put a meta first?
- dompdf re-parses HTML with libxml and reads any text before the charset meta as Latin-1.
- So `erp_invoice_pdf()` strips HTML comments and puts `<meta charset="utf-8">` at the very start.

Related: docs/memory/core/file-header-and-embedded-libraries.md
