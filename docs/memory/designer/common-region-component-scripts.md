---
name: common-region-component-scripts
description: The block scripts (theme switcher, scroller, Listen, map, interactions…) run a second time after a converted common region or a designed pop-up is drawn
---

## Why do the block scripts run twice?
- The blocks carry a marker and no script; `get_page_content.php` adds each file in one pass per script (`pg_theme_switcher_inject()`, `pg_scroller_inject()`, `pg_listen_inject()`, `pg_place_list_inject()`, `pg_course_blocks_inject()`, `pg_site_push_block_inject()`, `pg_fx_inject()`, `pg_bs_tips_inject()`, `pg_ai_summary_inject()`, `pg_wishlist_inject()`, `pg_booking_widget_inject()`, `pg_cf_rich_text_inject()`).
- Those passes run once the page's components and widgets are drawn, but a common region made a shared component is drawn later, by the common region pass (`pg_common_region_component_html()`), and a designed pop-up's `<template>` later still. Before PR #11510 their blocks showed with no script: the switcher, the row buttons, Listen, the map and the interactions did nothing.

## How does it work now?
- The passes are one closure, `$pg_block_scripts`, run in place as before. The common region pass sets `$pg_block_scripts_again` when it draws a component; after the auto dialog script and templates are written, the closure runs again when that flag is set or `$auto_dialog_templates` is not empty. A page with neither runs nothing more.
- Each pass adds its file only when the page holds its marker and not the file, so the second run adds only what the late component brought.
- The rich-text editor of designed forms has no such check (`pg_cf_rich_text_inject()` adds the editor for every area each time): the second run skips it when the first run met a `data-pg-rich-text` area (`$pg_block_scripts_rich_text`).
- Colour mode (`pg_design_color_mode_apply()`) is not a block script: it stays outside the closure and runs once, after the first run.

## What is not verified?
- A pop-up's blocks are copied into the dialog after the page loaded; whether each script binds blocks added later depends on that script. The file is on the page either way.

## Tests?
- `tests/common_region_component_scripts_test.php` (PR #11510, issue #11500) pins the closure, the flag and the order by source.
