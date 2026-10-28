---
name: bootstrap-6-redesign
description: Steps B10-B12 of plans/bootstrap-6-panel.md: token tuning, the redesign of legacy form screens and tables, jQuery UI out of the panel; decisions and open points
---

## How are the tokens tuned (B10)?
- After B9, one PR per token group (colours, radius, spacing, focus), with before and after screenshots of the liked screens. The owner approves each before merge.

## Which screens are redesigned (B11), and how?
- Legacy edit screens whose inputs spread over full-width rows or label/field tables. The B1b screenshot round lists them; the owner picks the order. The liked screens (`bootstrap-6-panel.md`) are the reference and are not redone.
- Target: the product_builder pattern (`ui/edit-screen-wide-layout.md`: form column, sticky status column, sticky save bar) with v6 `form-field`, form-adorn and cards. One screen per PR, before and after screenshots, the owner's approval before merge.
- Tables: the owner finds their structure fine. `table.chart` and DataTables stay; density, header, row actions and empty states may change (`.table-card` once B0b is in).

## What leaves with jQuery UI (B12)?
- After B9c no jQuery UI picker is left in the panel. Then `.sortable()` (backend.src.js `.pg-sortable`, product_builder.js, welcome.php `#widgets`, the view_menu_items.php tree) and `.dialog()` (`#edit_cell_properties` in backend.src.js, theme_designer.php, get_theme_designer_module.php) go, and with them the jQuery UI sheets and script in `output_control_panel_header_includes()`.
- Progress: the `#edit_cell_properties` layout-cell dialog in backend.src.js was dead code (`initialize_style_designer()` had no caller) and is removed (#2360). The theme designer dialogs (theme_designer.php, get_theme_designer_module.php) and the `.sortable()` replacement wait on the owner; jQuery UI stays in the panel header until both are done.
- Timepicker add-on: no panel code calls `.datetimepicker()`, `.timepicker()` or `$.timepicker`. Removed from 12 screens and the backend `picker_scripts` of `get_form_content()` (#2510). Left (files held by other PRs when done): add/edit_calendar_event.php, add/edit_submitted_form.php, view_order.php, includes/settings/commerce.php; then its tag goes from each, the `get_*_picker_format()` calls stay.
- Dialogs move to `pgUI` dialogs. The sortable replacement (own pointer-event code or a library) is open: ask the owner; a library goes through `core/`. Rewrite `ui/sortable-init-pitfall.md`.
- Site pages keep loading jQuery UI. Until B12 jQuery UI owns `$.fn.tooltip` under v6, so panel code never calls `$().tooltip()` or `$().popover()` after B2.
- Sortable: decided in #2511 (option 2), vendored SortableJS through `core/` (`core/sortablejs-library.md`). welcome.php, product_builder.js and the view_menu_items.php tree moved (#2538). Left for the last slice, with the jQuery UI includes: the `.pg-sortable` block in backend.src.js and the `.ui-sortable-handle` cursor rule in backend.src.css.
