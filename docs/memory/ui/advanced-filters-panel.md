---
name: advanced-filters-panel
description: The Advanced Filters panel's parts (OL2b): 48 px head with the count pill and Close, a body that alone scrolls with a 6 px bar, a foot with Clear and Update, 300 px right column from 1280 px
module: advanced_filters
---

## How is the panel put together?
- `.advanced-filter-bar` is a flex column: `pg_advanced_filters_head()` (48 px: `bi-funnel`, "Filters", the count pill, Close as `pg-icon-button-sm`), the form `.advanced-filter-body` holding `.advanced-filter-scroll` (the fields; the only part that scrolls, 6 px bar) and `pg_advanced_filters_foot()` ("Clear" `pg-button-quiet` at the left, "Update" `pg-button-fill` last).
- Helpers: `includes/fn/advanced_filters.php`, loaded by the five screens with `require_once`. Test: `tests/advanced_filters_panel_test.php`.

## Why was it redrawn?
- Owner (2026-11-22, agent channel message 12057, All Orders with Filters open): "burada da hatalar var". Update was a `position-sticky` button with nothing behind it, floating over the fields ("Address 1"); from 1280 px the whole panel scrolled with the browser's thick bar; labels were 16 px `form-label`.
- The panel ended at about half the list: its height was `100vh - 56px` while the top bar is `--pg-navbar-h` (50 px) and a sticky float only follows inside its parent. Now `100dvh - var(--pg-navbar-h)` with a `100vh` fallback.

## How wide and where?
- From 1280 px: a sticky right column, 300 px, from under the top bar to the window's bottom; the foot is static there.
- Narrower: a block over the list that grows with its fields; the foot is `position: sticky; bottom: 0`, so it stays at the window's bottom while the panel is on screen.

## What do the count and Clear read?
- `pg_advanced_filters_count($state, $empty)` counts the kept values that differ from their empty value; a value never set does not count. Payment method and Address Type have "None" = '', so their empty value is 'any'; Date Type's is `order_date`.
- `pg_advanced_filters_clear_url($action, $empty, $keep)` sends every field at its empty value plus `submit_data=Update`, the request an emptied form sends: no new parameter. The period (`start_*`, `stop_*`) belongs to the bar's period navigator and is kept.
- All Orders' fields: `pg_order_advanced_filters_empty($affiliate, $multicurrency, $shipping)`; the test checks it names every field the panel draws.

## Which screens have what?
- All Orders: head with the count, labels `pg-field-label`, controls `pg-input` (the date selects keep `form-control`, `data-pg-date-selects` turns them into a picker), Clear and Update.
- Contacts, Submitted Forms, Commissions, Site Log: the same head (no count), scroll and foot with Update only (`pg_advanced_filters_foot()` with no address draws no Clear); labels `pg-field-label`, controls still `form-control`. Their count and Clear wait for a list of their fields.

Related: docs/memory/ui/order-filter-names.md
