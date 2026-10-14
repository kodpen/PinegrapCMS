---
name: settings-card-chrome
description: Settings dialog visual details: acrylic glass mode (advanced-visuals, pg-settings-open) and the alert-dismissible padding rule.
---

## How does the settings dialog behave with the acrylic effect on?
- If Appearance -> Transparent Acrylic Effect is on, the dialog is glass too. The setting puts `advanced-visuals` on the body and every dropdown marked `.backdrop` is drawn through that filter. The settings dialog is the same kind of object (you return to the screen you laid it over when you close it) and is drawn with the SAME filter values, not its own recipe.
- The scrim behind it is lightened too: `.pg-settings-open` is a body class set by the dialog itself (the neighbouring `pg-side-panel-open` is shared with the designer panel, so it could not tell them apart). Frosted glass over an opaque black sheet is not frosted glass.
- Cards drop their fill: in this theme a card is already one step from the pane (measured rgb(16,17,20) over rgb(16,17,19)), so it is the border, not the colour, that draws the card.
- With the switch off every surface is opaque as before.

## Can `alert-dismissible` carry `py-*`?
- No. Bootstrap parks the close button absolutely at the top right and gives it its own padding; shortening the alert pushes the button out of the box.
- On info alerts without a close button `py-2` is fine.

Related: docs/memory/ui/settings-card-row-layout.md
