---
name: bootstrap-6-redesign
description: Steps B10-B12 of plans/bootstrap-6-panel.md: token tuning, the redesign of legacy form screens and tables (open), jQuery UI out of the panel (done); decisions and open points
---

## How are the tokens tuned (B10)?
- After B9, one PR per token group (colours, radius, spacing, focus), with before and after screenshots of the liked screens. The owner approves each before merge.

## Which screens are redesigned (B11), and how?
- Legacy edit screens whose inputs spread over full-width rows or label/field tables. The B1b screenshot round lists them; the owner picks the order. The liked screens (`bootstrap-6-panel.md`) are the reference and are not redone.
- Target: the product_builder pattern (`ui/edit-screen-wide-layout.md`: form column, sticky status column, sticky save bar) with v6 `form-field`, form-adorn and cards. One screen per PR, before and after screenshots, the owner's approval before merge.
- Tables: the owner finds their structure fine. `table.chart` and DataTables stay; density, header, row actions and empty states may change (`.table-card` once B0b is in).

## Is B12 (jQuery UI out of the panel) done?
- Yes, #2762: the panel header loads no jQuery UI; before it the pickers (B9c), the timepicker add-on (#2510 … #2639), the sortables (SortableJS, #2511, #2538), the layout-cell dialog (#2360) and the theme designer entries (#2512, #2542) left. What stays true is in `ui/panel-without-jquery-ui.md`; theme_designer.php and get_theme_designer_module.php stay on the disk unlinked (#2437).
