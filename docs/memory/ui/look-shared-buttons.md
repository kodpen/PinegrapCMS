---
name: look-shared-buttons
description: LM2 (#9680): the toolbar's primary pill and labelled text buttons as look A's .pg-button, the save bar's buttons by role; what was left and why
module: output
---

## What changed in the toolbar?
- `pg_screen_toolbar()` draws a slot's button array as `pg-button pg-button-fill` (primary) and `pg-button pg-button-ghost` (every other slot), the icon without `me-*`. Pinned by `tests/screen_toolbar_test.php`.
- The old primary pill `btn btn-sm btn-solid theme-primary rounded-pill px-5` (also `px-4`, `px-12`) is `pg-button pg-button-fill` in every panel file outside Workspace, the installer and kept screens' own markup; extra classes (`no-submit`, `pg-menu-caret`, `text-nowrap`) stay.
- Labelled text buttons in toolbar slots (ERP invoice, quote and purchase order mail, PDF and waybill; audit log; cart quotes; form fields; Translations) are `pg-button pg-button-ghost`.
- `#button_bar` and `.pg-toolbar` give a `.pg-button` `flex: 0 0 auto` and zero an icon margin left in markup (block "Page action bar" of `backend.src.css`).

## What did it leave as it was?
- Workspace screens and scripts (DL5f keeps Workspace out while its plans run) and `install/index.php` (no panel stylesheet).
- The own markup of kept screens (`workflow/decisions-made-panel-3.md`): Edit User's Sessions drawer Done and the Offers head's New Offer keep the old pill; the shared CSS and `pg_screen_toolbar()` output still reach them. Architect (2026-11-15): asked of the owner, back as a small PR on a yes.
- Icon-only toolbar buttons (the three-dots menu, Up, previous and next arrows): in the sheet an icon button is `.pg-icon-button`, a question for the architect.
- `includes/erp/order_flow.php` step rows and the Find buttons of `privacy_export.php` / `privacy_erase.php`: not toolbars, they pair the primary with Bootstrap outline buttons; they move with their groups.

## How is the save bar drawn?
- By role in the block "Look A: add and edit screens", markup untouched: `ui/look-edit-screens.md`, "What is the save bar?".

Related: docs/memory/ui/admin-screen-toolbar.md, docs/memory/ui/look-edit-screens.md
