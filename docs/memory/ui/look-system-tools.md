---
name: look-system-tools
description: The system tools in look A (S6a of panel-old-screens, #10533): section titles, card tables, field spans, measure, what stayed and what was held
---

## Which screens moved?
- Database Engine (`database_engine.php`), Find and Replace, Mass Edit, Repair Software (`software_repair.php`), Backups, Experiments, Import (`import.php`), Migration, API Documentation (`api_docs.php`), Developer Lock (step S6a of the plan panel-old-screens).
- Translations and Clean Up (`translations.php`, `clean_up.php`) were held for S6c: open PRs changed them at the time (#10523, #10528).
- Software Updater (`software_update.php`) needed nothing: it opens in the settings dialog, and what it draws its own way is the update's hero (glyph, the two version figures), a figure outside the report block (`plans/panel-old-screens-rules.md`).

## What changed on them?
- Card titles: the upper-case `text-uppercase h5|h6 fg-primary fw-bold` and the plain `card-header` titles are `h2.card-header.bg-reset.border-0.pg-title-section`. A header that also holds a note, a code, a badge or the search box keeps its flex div (`bg-reset border-0`) with an `h2.pg-title-section mb-0` in it (Database Engine's Visitor records, Table health, All tables; Find Results' group cards).
- Find and Replace's Scope header is a collapse button, so its title is a `span.pg-title-section` (a heading cannot sit in a button). Find, Replace and Developer Lock's PIN CODE keep their `label` inside the `h2`.
- Mass Edit's page cards: the page name is the card's `h2` title; its glyph left the title and the inline flex style became `d-flex align-items-center min-w-0`. Apply to Fields is `legend.form-label.pg-field-label`; the /pattern/flags line `pg-text-meta` (was an inline .75em).
- Repair Software got `pg-measure-edit pg-measure-single` (no status column). Its version select sits on the grid, `col-12 md:col-6`, the button beside it (was an inline 28rem cap).
- Migration: API URL the whole row (a web address), Email and Password 6 + 6; the https help lost `text-end`.
- Import: the export file chooser is `col-12 pg-field-span-6`, as the CSV chooser of S1. The column mapping grid keeps its 6 + 6 selects (`look-mapping-grids.md`).
- Tables straight in a card carry `.pg-card-table` (`look-content-screens.md`): Database Engine's Tables on MyISAM and Table health (their card body is `p-0` only when it holds the table; the alerts keep the padded body), Find Results, Backups' Copies in the bucket, Experiments' list and variants.
- API docs: the Try as select's 220 px moved from `style=` into the screen's `<style>` block, where the phone rule already overrides it.

## What stayed as it was?
- Buttons (toolbar, row, card and save bar buttons), switches and radios.
- Database Engine's side figures and Import's Summary values (figures outside the report block), the Backups list (already on the table driver), the daily figures table inside Experiments' details, Import's result tables in a padded card body.
- Inline: the sticky column's `top: 3.3rem` and the save bar column's `bottom: 0`, progress bar widths (scripts set them), the popover arrow's position, Repair Software's anchor `scroll-margin-top`, an Experiments variant's colour dot (the chart's series colour), the Experiments name cell's 10rem, Find Results' open-in-new glyph size, Mass Edit's `min-height: 60vh`.
- Experiments' variant letter cell (upper case, bold), as Page tests in S1.

Related: docs/memory/ui/look-menus-regions-screens.md, docs/memory/ui/look-content-screens.md, docs/memory/ui/look-field-widths.md
