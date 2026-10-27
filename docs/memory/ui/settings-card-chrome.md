---
name: settings-card-chrome
description: Settings dialog visual details: acrylic glass mode (advanced-visuals, pg-settings-open) and the pg-alert-dismissible padding rule.
---

## How does the settings dialog behave with the acrylic effect on?
- If Appearance -> Transparent Acrylic Effect is on, the dialog is glass too. The setting puts `advanced-visuals` on the body and every menu marked `.backdrop` (`.menu.backdrop`) is drawn through that filter. The settings dialog is the same kind of object (you return to the screen you laid it over when you close it) and is drawn with the SAME filter values, not its own recipe.
- The scrim behind it is lightened too: `.pg-settings-open` is a body class set by the dialog itself (the neighbouring `pg-side-panel-open` is shared with the designer panel, so it could not tell them apart); while it is on, `.dialog` gets `--bs-dialog-backdrop-bg: rgba(0, 0, 0, 0.25)`. The card's own dialogs in `#pg_settings_modal_extras` are frosted the same way. Frosted glass over an opaque black sheet is not frosted glass.
- Cards drop their fill: in this theme a card is already one step from the pane (measured rgb(16,17,20) over rgb(16,17,19)), so it is the border, not the colour, that draws the card.
- With the switch off every surface is opaque as before.

## Can `pg-alert-dismissible` carry `py-*`?
- No. Bootstrap 6 has no `alert-dismissible`; the panel's `pg-alert-dismissible` (shell rules in `backend.src.css`) keeps v5's: `padding-right: 3rem` and the `.btn-close` placed absolutely at the top right with its own padding (`1.25rem 1rem`). Shortening the alert pushes the button out of the box.
- On info alerts without a close button `py-3` (v5's `py-2`, same 0.5rem) is fine.

Related: docs/memory/ui/settings-card-row-layout.md
