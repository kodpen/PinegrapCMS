---
name: dompdf-embedding
description: How dompdf and its chain are embedded in includes/dompdf/: versions, custom autoloader, runtime write location, HTML/charset handling for PDFs.
---

## What is embedded and in what versions?
- `includes/dompdf/`: dompdf 3.1.6 and its chain: php-font-lib 1.0.2, php-svg-lib 1.0.2, html5-php 2.10.1 (pinned, see PHP base rule), PHP-CSS-Parser 8.9.0.
- Layout follows dompdf's release archive, under `vendor/`. The loader is Pinegrap-written `includes/dompdf/autoload.inc.php` (PSR-4 map, `PG_API_ENTRY` gate, no Composer).
- `tools/lint.php` skips the folder. Size ~11 MB, of which 7.6 MB is DejaVu fonts.

## Where may it write at runtime?
- Everything it writes (`fontCache`, `tempDir`) goes under `data/cache/dompdf/`, NEVER into `includes/` — `includes/` is part of the integrity hash.
- Same reason applies to any embedded library: one that writes into `includes/` at runtime shows as "tampered" every hour.

## Why does erp_invoice_pdf() strip comments and put a meta first?
- dompdf re-parses HTML with libxml and reads any text before the charset meta as Latin-1.
- So `erp_invoice_pdf()` strips HTML comments and puts `<meta charset="utf-8">` at the very start.

Related: docs/memory/core/file-header-and-embedded-libraries.md
