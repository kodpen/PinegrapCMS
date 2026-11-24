---
name: late-drawn-block-scripts
description: The block-script passes run again for a common region made a shared component and a designed pop-up, both drawn after them
---

## What does the page do?
- `get_page_content.php` holds the block-script passes in one closure, `$pg_block_scripts`: theme switcher, sideways rows, Listen, map of places, course blocks, Notify me, interactions, tooltips/popovers/tabs, in-browser summary, favourite hearts, booking widget, rich-text editor. It runs once after the shared components, widgets and custom PHP are drawn; colour mode follows it.

## Why does it run again?
- A common region made a shared component (`<cregion>` pass, `pg_common_region_component_html()`) and a designed pop-up (`pg_auto_dialog_templates()`, before `</body>` with the auto-dialog script) are drawn later. Before PR #PRNUM neither got any script its blocks need (issue #11500): the switcher, the row buttons, Listen, the map and interactions did nothing.
- Now the closure runs again after the common region pass when a drawn component holds a marker, and after the pop-up templates when they hold one.

## What decides the rerun?
- `pg_block_scripts_needed($html)` (`includes/fn/designer.php`, pure): any of the passes' `data-pg-*` markers, or `pg_bs_tips_pattern()`. A new block-script pass adds its marker there.

## Why is a rerun safe?
- Every pass adds its file only once: it looks for its own script address or style id. `pg_cf_rich_text_inject()` prints `pg_cf_rich_text_marker()` (`<!--pg-rich-text-editor-->`) before the editor's code and stops when it finds it, so a rich-text field only in a converted region or a pop-up, while the page already has one, gets no second editor and is not started.

## What is not covered?
- A date field in a designed pop-up: `pg_site_datetime_inject()` runs once, before the pop-up is drawn (`tests/site_datetime_test.php` pins one call). A converted common region gets it, since that pass runs after the common regions.
- The scripts are loaded for a pop-up; whether each one binds elements copied from the `<template>` after load depends on that script.

## Tests?
- `tests/region_component_block_scripts_test.php`.
