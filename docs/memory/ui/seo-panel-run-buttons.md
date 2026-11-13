---
name: seo-panel-run-buttons
description: SEO detail panel and SEO card: Refresh score / Analyze HTML run the link step, work on every edit screen, update every score shown; counter measures like the score; missing page is not found
---

## What does "Analyze HTML" run for one record?
- `pg_seo_analyze_record()` (`seo_structure.php`) loads `seo_links.php` itself, so the page and catalog passes resolve the links, write `seo_link`, store the `links` findings and (pages) `seo_link_score`, as the nightly batch does. Before, the `function_exists('pg_seo_process_links')` guard skipped the step silently for the panel button and every save-time call (#5845).

## Which screens print the buttons' click handler?
- `pg_seo_render_checklist()` draws the buttons only; the handler is `pg_seo_render_run_script()` (printed once per page). The Pages and Products lists print it for the analysis in their Details panel (the SEO drawer is gone, #PRNUM); Edit Page, `edit_product_group.php` and `product_builder.php` print it beside the checklist. The product screens did not, and the buttons did nothing (#5846).

## How does a refresh update the other scores on the screen?
- With `fragment=checklist`, `get_seo_analysis.php` also sends `pg_seo_render_fresh_indicators()`: two inert `<template data-pg-seo-fresh="badge|bar" data-pg-seo-key="type:id">` holding the new badge and list bar.
- The handler copies each into every element whose `data-pg-seo-badge` / `data-pg-seo-bar` equals the key: the card heading badge on the three edit screens, the bar and badge links of the Pages list and the bar of the Products list (#5849).

## Which colour is the panel's large score?
- The band's `ink` from `pg_seo_score_color()`, as in `pg_seo_render_bar()`; the fill `#fd7e14` read 2.36:1 on the light ground (#5848).

## How does the live character counter measure?
- `pgSeoMeasuredLength()` (`backend.src.js`): PHP's `trim()` set removed from both ends, a surrogate pair counted once, like `pg_seo_normalize_entity()` + `mb_strlen()`. The raw UTF-16 length showed 150 ✓ while the checklist scored 149 (#5847).

## What does the panel say for a page id that does not exist?
- "Sorry, we could not find that record." without a log line, like products. The access-denied log line is written only for an existing page the user cannot edit (#5850).

Test: `tests/seo_panel_run_bugs_test.php` (also the Turkish lines of #5851).
