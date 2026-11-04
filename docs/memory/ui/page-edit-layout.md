---
name: page-edit-layout
description: Create Page and Edit Page on the wide edit screen layout: form column, sticky status column (page type card, Edit Page's Summary card with the facts and SEO badge), save bar column
---

## How are Create Page and Edit Page laid out?
- As `edit-screen-wide-layout.md` asks (#6535): form column `col-12 lg:col-8 2xl:col-9 order-1` (Main Informations, Content Options, System Options, SEO; Edit Page adds Site Search and Comments), status column `col-12 lg:col-4 2xl:col-3 order-2` with one `position-sticky mt-7` box at `top:3.3rem`, save bar `col-12 order-3 position-sticky` (`bottom:0; z-index:3`).
- Before: the page type card was its own narrow sticky column beside full-width stacked cards (about 970 px wide at 1280, mostly empty), and Main Informations carried a `position-sticky` that never stuck because its column held only that card.

## What sits in the status box?
- The page type card: `pg_page_form_type_fields()['selector']` is a bare `card mb-7`, no column and no stickiness; the screen's box holds those. On Edit Page it is empty when the user may not set the type.
- Edit Page only: the Summary card. Its header carries the SEO badge (`data-pg-seo-badge="page:<id>"`, refreshed by the checklist's run buttons); its list holds Access (with the home badge) and the `$output_subnav_*` facts, each built as an `<li>`. The facts left the toolbar's grow slot, so the toolbar is drawn only when it has buttons.

## What stayed?
- Element ids, the System Options card (`#options_row`), the save and delete buttons and their names. The inline handlers, hidden rows and fake popovers are #6534's.

Pinned by `tests/page_edit_layout_test.php` and `tests/panel_narrow_layout_test.php`. Not measured in a browser.
