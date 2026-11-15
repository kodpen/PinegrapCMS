---
name: look-icon-buttons
description: SH5: icon-only toolbar buttons and menu toggles of the old panel screens as look A's .pg-icon-button with a lang() name; one filled button per view on Marketplace Settings; what was left and why
---

## Which buttons are icon buttons?
- An icon-only button or link in a screen's toolbar or head is `.pg-icon-button` (32 px), never `btn btn-sm btn-text theme-secondary`: the More (three dots) menu toggles, the period stepper's Previous and Next (`includes/fn/output.php`), the advanced filters toggle, the settings screen's Previous and Next category, the Files and Media toolbar (Import / Export, Up, Filters, Grid view, List view, Recycle Bin, More).
- Extra classes stay (`no-submit`, `no-popover`, `position-relative`, `d-none`); `btn-group` around two view toggles becomes `d-inline-flex gap-1`.
- The icon carries `aria-hidden="true"`; the button carries `aria-label` (and `title`) from `lang()`, escaped with `h()`.

## How is a pressed or disabled icon button drawn?
- Pressed: `aria-pressed="true"`, `aria-expanded="true"` or `.is-active` (never `.active`: the recipe does not read it). The advanced filters toggle of Submitted Forms sets ` is-active`.
- Disabled: the recipe has no disabled look, so a disabled icon button adds `opacity-50` (settings screen's first and last category).

## Which filled buttons changed?
- Marketplace Settings had three filled buttons in one view; only the toolbar's New stays `pg-button-fill`. The status column's Settings is `pg-button-quiet pg-button-sm`, Test connection `pg-button-ghost pg-button-sm`; the Matched products card's Match in bulk with pictures (or Products in this shop when the connector cannot read the catalogue) is quiet, the rest ghost.

## What was left as it was?
- Row actions of list tables (`btn-data-control`, row links): the list row steps.
- Site output: `booking_manage.php` is a visitor's page on Bootstrap 5.3.8, not panel.
- Files held by open PRs when SH5 was cut (view_orders, view_commissions, marketplace_products, edit_erp_invoice, erp_receipt, view_log, edit_contact, view_contacts): a later step.
- The owner's kept screens (`workflow/decisions-made-panel-3.md`) and Workspace; the designer (`includes/designer_screen.php`).

Related: docs/memory/ui/look-shared-buttons.md, docs/memory/ui/look-type-and-recipes.md
