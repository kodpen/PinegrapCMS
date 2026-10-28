---
name: panel-without-jquery-ui
description: The panel loads no jQuery UI (#2762): what is undefined on panel screens, what replaces each widget, the test that keeps it out, who still loads it
---

## What does the panel load?
- `output_control_panel_header_includes()` loads jQuery without jQuery UI since #2762 (step B12 of the Bootstrap 6 plan). Every screen printed through `output_header()` or `output_header_secure()` has no `$().dialog()`, `.sortable()`, `.draggable()`, `.effect()`, `.datepicker()`, `.disableSelection()`, `$.ui` or `$.widget`.
- Bootstrap 6 has no jQuery plugins either, so `$().tooltip()` and `$().popover()` are undefined too (jQuery UI's `$.fn.tooltip` owned that name until #2762).

## What replaces each widget?
- Dialogs and drawers: `pgUI.dialog` / `pgUI.drawer` (`pg-ui-adapter.md`). Hints: `pgUI.tooltip` / `pgUI.popover` (`panel-hint-tooltip-or-popover.md`).
- Drag to reorder: SortableJS per screen (`sortable-init-pitfall.md`, `core/sortablejs-library.md`).
- Date and time fields: `pg_fields.js` wrappers (`pg-field-wrappers.md`).
- Effects: the Web Animations API or CSS. The image picker's "already picked" shake (`backend.src.js`) runs `element.animate()` and is skipped under `prefers-reduced-motion`.

## Who still loads jQuery UI?
- Site pages (`get_page_content.php` and the site renderers, `frontend.src.js`): sites stay on Bootstrap 5.3.8 + jQuery UI. `assets/lib/Jquery/ui` stays.
- `theme_designer.php` and `get_theme_designer_module.php` call `.dialog()` but are unlinked (#2437, #2512).

## What keeps it out?
- `tests/bs6_jquery_ui_panel_test.php`: the header prints no `Jquery/ui/` file, and no PHP or JS file outside its site and theme designer list calls a jQuery UI widget chained off a call (`).dialog(`, `).effect(` …) or `$.ui` / `$.widget`. A new site renderer that uses jQuery UI joins the list.

Related: docs/memory/ui/bootstrap-6.md, docs/memory/ui/pg-ui-adapter.md, docs/memory/ui/sortable-init-pitfall.md
