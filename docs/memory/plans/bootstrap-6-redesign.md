---
name: bootstrap-6-redesign
description: Steps B10-B12 of plans/bootstrap-6-panel.md: token tuning, the redesign of legacy form screens and tables, jQuery UI out of the panel; decisions and open points
---

## How are the tokens tuned (B10)?
- After B9, one PR per token group (colours, radius, spacing, focus), with before and after screenshots of the liked screens. The owner approves each before merge.

## Which screens are redesigned (B11), and how?
- Legacy edit screens whose inputs spread over full-width rows or label/field tables. The B1 screenshot round lists them; the owner picks the order. The liked screens (`bootstrap-6-panel.md`) are the reference and are not redone.
- Target: the product_builder pattern (`ui/edit-screen-wide-layout.md`: form column, sticky status column, sticky save bar) with v6 `form-field`, form-adorn and cards. One screen per PR, before and after screenshots, the owner's approval before merge.
- Tables: the owner finds their structure fine. `table.chart` and DataTables stay; density, header, row actions and empty states may change (`.table-card` once B0b is in).

## What leaves with jQuery UI (B12)?
- After B9c no jQuery UI picker is left in the panel. Then `.sortable()` (backend.src.js `.pg-sortable`, product_builder.js, welcome.php `#widgets`, the view_menu_items.php tree) and `.dialog()` (`#edit_cell_properties` in backend.src.js, theme_designer.php, get_theme_designer_module.php) go, and with them the jQuery UI sheets and script in `output_control_panel_header_includes()`.
- Dialogs move to `pgUI` dialogs. The sortable replacement (own pointer-event code or a library) is open: ask the owner; a library goes through `core/`. Rewrite `ui/sortable-init-pitfall.md`.
- Site pages keep loading jQuery UI. Until B12 jQuery UI owns `$.fn.tooltip` under v6, so panel code never calls `$().tooltip()` or `$().popover()` after B2.
