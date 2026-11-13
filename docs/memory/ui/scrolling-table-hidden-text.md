---
name: scrolling-table-hidden-text
description: Every panel .table-responsive is position: relative, so a .visually-hidden heading or label and a combobox's posted select stay inside the scroll box instead of widening the page on a phone (#9079)
---

## Why is every `.table-responsive` positioned?
- `.visually-hidden` (an Actions heading, "No earlier figure", a status word) and `.pg-combobox-native` are `position: absolute`. Bootstrap 6's `.table-responsive` scrolls (`overflow-x: auto`) but is static, and its `container-type: inline-size` makes it no containing block either.
- Their containing block was then the nearest positioned box outside the scroll box (a card such as Edit User's `#store_credit`, or the page). The box's overflow did not clip them, they sat where their cell is, past the box's right edge, and the whole page scrolled sideways at 390 px: Edit User with a store credit card 47 px, Visitors' landing pages table 6 px (#9079).
- `backend.src.css` gives `.table-responsive` `position: relative`, the general form of the earlier `.table-responsive:has(.pg-combobox-native)` (#5585, `erp/erp-bank-statement-matching.md`). The list table driver's `.pg-list-table-scroll` has the same rule for the same reason.

## What does it change elsewhere?
- An absolutely placed element inside a scrolling table is now clipped by the table's box. Dropdowns already sat in a positioned `.dropdown` or `.btn-group` inside the box, tooltips and popovers are appended to `<body>`, the legacy "collapse popover" panels are in the flow: none of them moved.
- No stacking context: `position: relative` with `z-index: auto` creates none.
- Other scroll boxes that are not `.table-responsive` keep their own rule, e.g. the calendar's month grid `div.monthly` (#9035).

## How was it checked?
- A static page with the panel's Bootstrap 6 and `backend.min.css` at 390 px: a card holding a scrolling table with a hidden last heading measured 481 px before, 390 after. Not measured on dev.pinegrap.com.

Tests: `tests/panel_narrow_layout_test.php` (`test_panel_narrow_layout_scrolling_table_holds_hidden_text`), `tests/erp_bank_statement_bugs_test.php`. Related: toolbar-phone-fit.md
