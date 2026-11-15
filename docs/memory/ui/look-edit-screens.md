---
name: look-edit-screens
description: Add and edit screens in look A (DL5c, #8934): the edit width on every add/edit screen, columns 1300 and 376 from the shared classes, look cards and titles, the floating save bar; what is not done yet
---

## Which screens are on it?
- Every `add_*.php` and `edit_*.php` that prints the form and status columns (`lg:col-8 2xl:col-9 order-1`, `lg:col-4 2xl:col-3 order-2`), itself or through `includes/forms/`, plus `approve_affiliate.php`, `erp_receipt.php`, `erp_cheque.php`: `pg-measure-edit` in their `'extra classes'` (`look-page-widths.md`).
- Not on it: screens with no `'extra classes'` (the code editors `edit_custom_style.php`, `edit_javascript.php`, `edit_theme_css.php`, `edit_page_layout.php`, `edit_contacts.php`), imports, tools and the order and return records (`view_order.php`, `view_return.php`: their item tables need more than 760 px); they move with their family. The settings page takes `pg-measure-edit` with its own cards (`look-settings-screens.md`).
- `tests/look_edit_screens_test.php` refuses an add or edit screen with the columns but without the class.

## How are the columns sized?
- Block "Look A: add and edit screens" at the end of `backend.src.css`. From 1024 px the status column is `flex: 0 0 calc(var(--pg-measure-status) + var(--pg-measure-gap))` (376 px plus the two 12 px gutter paddings) and the form column `flex: 1 1 0%`: at the full 1700 px measure 1300 and 376 with the 24 px gutter; narrower, the form column gives way first; below lg they stack as before. No row needs `pg-edit-columns` (only `product_builder.php` keeps that grid).
- Both columns carry `min-width: 0`, or content that does not wrap (state comboboxes, a long address) widened the page past the window (#9107, `grid-column-min-width.md`).

## What do the cards and titles look like?
- The cards straight in the form column (`.order-1 > .card`) and in the status column's sticky box (`.order-2 > .position-sticky > .card`) are the look card: their selectors sit beside `.pg-card` in the recipe block. 16 px apart; padding 20 / 22 (16 / 18 in the status column).
- Card titles in `includes/forms/` and the add/edit screens are `h2.card-header.bg-reset.border-0.pg-title-section` (17 / 650, sentence case); in the status column the block sets the status title (15 / 650). The old `text-uppercase h5 fg-primary fw-bold` had to leave the markup: Bootstrap 6's utilities layer comes after `custom`, so no panel rule can beat them.

## How wide is a field?
- Architect, 2026-11-15: a date or a time 3 columns (3 + 3 side by side), a date with its time in one input 4.
- Architect, 2026-11-15: a phone 4, an e-mail 6, a web address the whole row.
- Architect, 2026-11-15: spans from md (768 px), whole row below; a value cut at 1024 px may take the next span up from lg.
- Architect, 2026-11-15: one 16 px column gap rule; `my-3` leaves all fields in one later step.
- Architect, 2026-11-15: a narrow field alone in its row keeps the row (`pg-field-span-N`): label on one line, help the row's width, only the control spans. How: `look-field-widths.md`.

## What is the save bar?
- The shared `nav.buttons.navigation` straight in the `order-3` column is the floating bar; a bar left in the form column or in `order-4` stays the old centred pill. Under a full-width table the bar's column is a second `order-3` after the table's (product group) (selector beside `.pg-floatbar`): from 576 px 56 px high, buttons at the right, 34 px high with 10 px corners (the shared bar's pill `!important` is overridden). A phone keeps the shared two-per-row bar.

## What is not done yet?
- The card's line under the title. Field widths on screens other than the product and page screens: with their group of the panel-old-screens plan.
- The bar's buttons keep their Bootstrap colours, not fill, quiet and ghost; no unsaved note at its left.
- `product_builder.php`'s own section titles stay its own.

Related: docs/memory/ui/look-page-widths.md, docs/memory/ui/look-edit-screens-wrapped-cards.md, docs/memory/ui/look-field-widths.md
