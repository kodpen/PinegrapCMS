---
name: dashboard-v2-phone-look
description: The new dashboard on a phone as drawn (picture 20, V6): the edit bar band, Edit dashboard at the left, card tools on two lines, the switch resting in edit mode, the differences kept
---

## What does the phone layout change?
- Under a 38rem dashboard (`@container pgdash (max-width: 37.99rem)`, a `max-width: 639.98px` window rule without container queries) in `assets/css/pg_dash.src.css`, "Phone" block:
  - The edit bar is a band across the screen: `margin: -.25rem -1rem 1rem` takes back #content's 1rem padding, no corner, only the rule under it, `.75rem 1.5rem` padding. As a rounded card inside the padding, Save wrapped onto its own line; the band keeps Reset, Cancel and Save on one row at 390 px.
  - "Edit dashboard" starts its own line at the left (`.pg-dash-switch-end` loses its auto margin).
  - A card's remove button opens the tool row's second line (the drawings' spacer pushes it there) instead of standing alone at the far end.
- Every width: in edit mode `editButton()` in `pg_dash_edit.js` disables `#pg_dash_switch` (and enables it on leaving): the switch reloads the page and would drop the unsaved layout. A disabled switch is drawn dim on the field colour.

## What differs from the picture on purpose?
- "Ön ayar" and "İptal Et" stay: they are the panel's shared `Preset` and `Cancel` translations; the picture says "Hazır düzen" and "Vazgeç".
- The greeting's chips, tiles and their notes are the sandbox's data; the System Status gauge is kept as it is.

## What checks it?
- `tests/dashboard_v2_phone_test.php`; the screen was shot at 390 px light and dark and 1280 px dark on the sandbox, no horizontal scroll.

Related: docs/memory/ui/dashboard-v2-width.md, docs/memory/ui/dashboard-v2-phone-keyboard.md, docs/memory/ui/dashboard-v2-edit-mode.md
