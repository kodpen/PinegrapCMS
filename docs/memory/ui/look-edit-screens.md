---
name: look-edit-screens
description: Add and edit screens in look A (DL5c, #8934): the edit width on every add/edit screen, columns 760 and 336 from the shared classes, look cards and titles, the floating save bar; what is not done yet
---

## Which screens are on it?
- Every `add_*.php` and `edit_*.php` that prints the form and status columns (`lg:col-8 2xl:col-9 order-1`, `lg:col-4 2xl:col-3 order-2`), itself or through `includes/forms/`, plus `approve_affiliate.php`, `erp_receipt.php`, `erp_cheque.php`: `pg-measure-edit` in their `'extra classes'` (`look-page-widths.md`).
- Not on it: screens with no `'extra classes'` (the code editors `edit_custom_style.php`, `edit_javascript.php`, `edit_theme_css.php`, `edit_page_layout.php`, `edit_contacts.php`), settings, imports, tools and the order and return records (`view_order.php`, `view_return.php`: their item tables need more than 760 px); they move with their family (DL5d settings).
- `tests/look_edit_screens_test.php` refuses an add or edit screen with the columns but without the class.

## How are the columns sized?
- Block "Look A: add and edit screens" at the end of `backend.src.css`. From 1024 px the two column classes take the row as `flex: 760 1 0%` and `flex: 336 1 0%`: at the full 1120 px measure exactly 760 and 336 with the 24 px gutter; narrower, both shrink in that ratio; below lg they stack as before. No row needs `pg-edit-columns` (only `product_builder.php` keeps that grid).

## What do the cards and titles look like?
- The cards straight in the form column (`.order-1 > .card`) and in the status column's sticky box (`.order-2 > .position-sticky > .card`) are the look card: their selectors sit beside `.pg-card` in the recipe block. 16 px apart; padding 20 / 22 (16 / 18 in the status column).
- Card titles in `includes/forms/` and the add/edit screens are `h2.card-header.bg-reset.border-0.pg-title-section` (17 / 650, sentence case); in the status column the block sets the status title (15 / 650). The old `text-uppercase h5 fg-primary fw-bold` had to leave the markup: Bootstrap 6's utilities layer comes after `custom`, so no panel rule can beat them.

## What is the save bar?
- The shared `nav.buttons.navigation` in the `order-3` column is the floating bar (selector beside `.pg-floatbar`): from 576 px 56 px high, buttons at the right, 34 px high with 10 px corners (the shared bar's pill `!important` is overridden). A phone keeps the shared two-per-row bar.

## What is not done yet?
- Field widths on the 12-column grid (a price 3 columns, a code 4, a select 6) and the card's line under the title: per-field markup, screen by screen.
- The buttons keep their Bootstrap colours (success, danger), not the look's fill, quiet and ghost; the unsaved note at the bar's left is not drawn.
- `product_builder.php`'s own section titles (icon, accent colour) stay its own.

Related: docs/memory/ui/look-page-widths.md
