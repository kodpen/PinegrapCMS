---
name: page-edit-layout
description: Create Page and Edit Page: wide layout (form, sticky status and save bar columns, Summary card), rows hidden with d-none via pg_page_row(), bindPageTypePicker(), no fake popovers
---

## How are Create Page and Edit Page laid out?
- As `edit-screen-wide-layout.md` asks (#6535): form column `col-12 lg:col-8 2xl:col-9 order-1` (Main Informations, Content Options, System Options, SEO; Edit Page adds Site Search and Comments), status column `col-12 lg:col-4 2xl:col-3 order-2` with one `position-sticky mt-7` box at `top:3.3rem`, save bar `col-12 order-3 position-sticky` (`bottom:0; z-index:3`).
- Before: the page type card was its own narrow sticky column beside full-width stacked cards (about 970 px wide at 1280, mostly empty), and Main Informations carried a `position-sticky` that never stuck because its column held only that card.

## What sits in the status box?
- The page type card: `pg_page_form_type_fields()['selector']` is a bare `card mb-7`, no column and no stickiness; the screen's box holds those. On Edit Page it is empty when the user may not set the type.
- Edit Page only: the Summary card. Its header carries the SEO badge (`data-pg-seo-badge="page:<id>"`, refreshed by the checklist's run buttons); its list holds Access (with the home badge) and the `$output_subnav_*` facts, each built as an `<li>`. The facts left the toolbar's grow slot, so the toolbar is drawn only when it has buttons.

## How are the type rows shown and hidden?
- Since #6534 every row of `page_form.php` and Edit Page's comment rows are hidden by a `d-none` class built server-side (`$…_row_style` is `' d-none'` or `''`, appended to the row's class); no inline `display`, which would beat the class.
- `change_page_type()`, `show_or_hide_*()` in `backend.src.js` toggle them only through `pg_page_row(id, shown)` (`classList.toggle('d-none')`, skips a row the screen does not print). Never `.style.display`, jQuery `show()`/`fadeIn()` on these rows.
- A box a `collapse-switcher` opens (`update_address_book_address_type_page_id_row`, `show_or_hide_search`, `show_or_hide_comments`) is the switcher's: `change_page_type()` never touches it, or its `d-none` would fight the switcher's inline display.

## What replaced the inline handlers?
- `bindPageTypePicker()`, called by both screens after their form: binds `#page_type` (its `data-original-type` is the saved type the "Save & Continue" check reads) and draws the chosen type's rows once; binds Edit Page's `#comments`. Without the picker (a user who may not set the type) the comment rows read `#comments`' `data-page-type`.
- The tinyMCE branches of the confirmation and return type switches are gone with their functions: the panel loads no tinyMCE there.

## What do the switch boxes look like?
- Site Search, Comments and the address type page are flat `collapse border rounded p-5` boxes, no popover classes or arrow. The three always-open boxes inside Comments (moderator, submitter, watcher e-mail) are plain headed rows (three nested borders at 375 px before). The other `.collapse.popover` callouts of `page_form.php` keep the panel-wide callout look.

Pinned by `tests/page_edit_layout_test.php` and `tests/panel_narrow_layout_test.php`. Not measured in a browser.
