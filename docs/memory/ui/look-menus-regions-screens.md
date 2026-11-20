---
name: look-menus-regions-screens
description: Menus, regions, ads, styles, theme and design files, the visitor and log screens in look A (S4, #10316): field spans per field, section titles, card tables, what stayed
---

## Which screens moved?
- Menu and Menu Item forms, Edit Menu's Sort Items card (`view_menu_items.php`); the common, designer, login and dynamic region forms; Ad and Ad Region forms; Page Style (`custom_style_form.php`); Add and Edit Theme File; Edit Design File.
- Visit Details (`view_visitor.php`), Site Log, Firewall log (`view_waf_log.php`), Performance (`view_performance_log.php`), Search Console.
- The lists (Menus, Regions, Ads, Styles, Themes, Design Files, Visitor reports) draw through the table driver and had nothing old but buttons.

## What changed on them?
- Fields (`look-field-widths.md`): every record name the whole row (Menu, Menu Item, the four region names, Ad, Ad Label, Page Style, Theme File, design File Name); Region Type alone at 6 (`pg-field-span-6`); Ad Sort Order, Transition Duration and Interval alone at 3 (`pg-field-span-3`). Link Target and Parent Menu Item stay 6 + 6, as do the two pop-up positions.
- Group headings inside a card (Login Region's Not Logged In and Logged In, Ad Region's Region Behavior) are `h3.pg-group-label`: a heading in a card never outranks its title (architect, channel 25 message 9282).
- Help text lost `text-end` (the class fields of Menu and Menu Item, Transition Duration).
- Card titles: the upper-case `text-uppercase h5|h6 fg-primary fw-bold`, the `div.card-header.pg-title-section` and Performance's `h5` with a glyph are `h2.card-header.bg-reset.border-0.pg-title-section`; a header holding a badge or a button keeps its flex div with an `h2.pg-title-section` in it. The glyphs left the titles (Performance, Possible False Positives) and so did the warning ink of Possible False Positives.
- Visit Details got `pg-measure-edit`: its columns and cards are the edit screen's.
- Site Log's "Date Range" filter heading is `.pg-group-label` (was green bold `h5`), as Contacts' Advanced Filters in S2.
- Tables straight in a card (Performance's three, Search Console's Searches and Pages) carry `.pg-card-table` (`look-content-screens.md`).
- Performance's query string line under an address is `pg-text-meta` (was an inline 0.72rem).
- `tests/panel_narrow_layout_test.php`: Edit Theme File may hold card titles as `h2.card-header`; any other `h2` is still refused.

## What stayed as it was?
- Buttons (toolbar, row, dialog and filter buttons), the Summary values, switches and radios.
- The figure tiles of the Firewall log, Performance and Search Console (a figure outside the report block, `look-sheet-silent.md`), Visit Details' value tiles.
- Edit Menu's item tree table: its own phone rule (dates hidden below md, the address breaking inside its cell) would clash with `.pg-card-table`'s 40rem.
- Menu Item's Page / or / URL trio (5 + 2 + 5 from xl), as the short link trio of S1.
- Audit Trail's filter card: a list's filters wait for the shared steps.
- The Firewall log's tables sit in a padded card body, not straight in the card.
