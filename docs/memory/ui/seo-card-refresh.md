---
name: seo-card-refresh
description: SEO card of the edit screens and the SEO Detail drawer: the two refresh buttons, which score indicators a refresh redraws, the panel score ink, the live counter's measure, a missing page
---

## Which screens print the buttons' handler?
- `pg_seo_render_checklist()` (root `seo.php`) draws 'Refresh score' and 'Analyze HTML' (`pg_seo_render_run_buttons()`), only markup. The click handler is `pg_seo_render_run_script()`, printed once per page: the list screens get it through `pg_seo_render_detail_offcanvas()`, `edit_page.php`, `product_builder.php` and `edit_product_group.php` print it beside the checklist.
- A new screen that draws the checklist prints it too. Before #5846 the product and product group screens did not, and their buttons did nothing.

## Does 'Analyze HTML' refresh the internal links?
- Yes. `pg_seo_analyze_record()` (`seo_structure.php`) loads `seo_links.php` itself, so its link step (`function_exists('pg_seo_process_links')`) runs for one record as it does in `pg_seo_analyze_batch()` and the nightly job: `seo_link` rows, the 'links' findings and `seo_link_score`. Before #5845 a single record (the button, a save, the designer) skipped it silently.

## Which score indicators does a refresh redraw?
- The panel (`.pg_seo_panel_wrap`) carries `data-seo-record="<type>:<id>"`. Every other indicator of the record is marked with `pg_seo_slot_attributes($type, $id, 'badge'|'bar')`: the card badges of the three edit screens, the bar and badge links of `view_pages.php`, the bar of the product list (`includes/fn/product_list.php`).
- `get_seo_analysis.php?fragment=checklist` sends the new `pg_seo_render_badge()` and `pg_seo_render_bar()` in `<template data-seo-slot="…">` beside the panel; the handler copies each into the marked elements of the same record. Before #5849 the card badge kept the old score.
- `view_folders.php` has its own copy of the handler for its injected fragment and does not redraw slots.

## Which colour does the panel score use?
- The band ink (`pg_seo_score_color()['ink']`), as `pg_seo_render_bar()`; the fill (`style`, #fd7e14) read 2.36:1 on the light ground (#5848).

## How does the live counter measure?
- `renderSeoCounter()` (`backend.src.js`) trims the characters PHP `trim()` strips and counts code points (`Array.from`), the measure of `pg_seo_normalize_entity()` + `mb_strlen()`. Counting the raw value made '150/160 ✓' beside '149 characters' (#5847).

## What does the panel answer for a page that does not exist?
- 'Sorry, we could not find that record.', like a product, and nothing is logged. Only an existing page the user cannot edit answers 'Access denied.' and writes the site log line (#5850).
