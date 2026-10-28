---
name: robots-large-image-preview
description: The site switch that adds max-image-preview:large to every indexed page's robots meta (config.max_image_preview_large, 11.0.51, #2175): default, new installs, where it is written
module: seo
---

## What does it do?
- `pg_page_robots_content()` (`includes/fn/seo.php`) adds `max-image-preview:large` after `noarchive` and `max-snippet:N` on every page in the index, items included. A noindex page never gets it (noindex wins and says only `noindex, follow|nofollow`). Without it Google Discover and results show only a small picture (#2175).
- Read from the constant `MAX_IMAGE_PREVIEW_LARGE` (`init.php`, false before the upgrade); the function's fifth argument overrides it, which the tests use.

## Which sites have it on?
- Decision #2471: one site-wide `config` column, on for new installs, off for running sites, no per-page override.
- Step 11.0.51 (`upgrade_2026_11_0_max_image_preview()`) adds it with DEFAULT 0, so an upgraded site writes exactly what it wrote before until the operator switches it on.
- `install/index.php` switches it on after the upgrade only for a starter site (`get_starter_site_language()` not ''), beside the legal documents switch; a restored backup keeps the value it was taken with.

## Where is it edited?
- Site Settings › SEO › Site Map and Crawling (`includes/settings/seo.php`, value from `prep.php`). `seo.save.php` writes it only when the column exists (`pg_schema_has()`) and the SEO card was posted.

Related: docs/memory/visitors/page-canonical-robots-directives.md
