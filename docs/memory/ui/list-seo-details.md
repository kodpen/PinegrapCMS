---
name: list-seo-details
description: A click on the SEO score of the Pages and Products lists loads the analysis into the Details panel under the SEO line (no drawer); the run buttons and failure lines there
module: list_table
---

## What does a click on the SEO score do?
- Owner (2026-11-13): the score no longer opens a drawer of its own; the analysis moves into the Details panel, because too many click targets made the table hard to use (channel 25 #2222).
- The cell's link carries `data-pg-details-load` (`get_seo_analysis.php?…&fragment=checklist`). `Driver.detailsFrom()` (`pg_list_table.js`) selects the row, opens the Details panel (the sheet below 1024 px) and loads the checklist under the SEO line (`dd.pg-list-table-details-more`, the panel's width); it stays while the panel redraws for that row and goes when another row is shown. The analysis loads only on that click: the endpoint recalculates the score.
- Refresh score / Analyze HTML work there through `pg_seo_render_run_script()`, which the two list screens print; the loaded fragment's `pg-details-loaded` event lets that script copy the fresh score into the row. Pinned by `tests/list_seo_details_test.php` (#PRNUM).
- Width with `pg-measure-list`, taken by every root list screen (`view_*.php` but the form designer, `erp_*.php` lists, `mail_queue.php`): as wide as its columns, 1700 px to the window (an open details panel added), centred, the name column taking the slack; the toolbar search is 300 px (`look-page-widths.md`). `tests/look_list_screens_test.php` pins both.

Related: docs/memory/ui/list-table-layout.md, docs/memory/ui/details-panel-rule.md, docs/memory/ui/seo-panel-run-buttons.md, docs/memory/ui/panel-load-failures.md
