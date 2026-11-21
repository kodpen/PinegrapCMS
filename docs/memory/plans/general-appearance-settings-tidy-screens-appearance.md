---
name: general-appearance-settings-tidy-screens-appearance
description: Plan general-appearance-settings-tidy, its second screen: the Appearance pane as folded rows, the Dashboard row by who it reaches, footer links as list rows in drawers
---

## By which rules?
- As `plans/general-appearance-settings-tidy-screens.md`: parts copied from the Design examples screen, its section in brackets; no part is new.

## How is the pane laid out?
- Head: "Appearance" and one purpose line [Type and text]: how the panel looks for everyone who uses it.
- Folded rows [Settings pane], one open, each line saying its state ("Acrylic on, panel CSS set", "Glass cards", "Latest editor", "Off"), in this order: "Panel look", "Dashboard", "Rich-text editor", "Private label branding"; a manager sees no Dashboard row, anyone but an administrator no Private label row (as today).
- Panel look: "Transparent acrylic effect" [Fields, switch] with "For everyone who uses the panel. Turn it off if the panel feels slow."; "Panel CSS" in the code editor, its line "Styles the control panel only, never the site" and, for an administrator, "A whole stylesheet can be set under Private label branding".
- Dashboard: a small label "For everyone" over Card style, Panel backdrop and "Reset widgets" quiet with its line; a small label "Only for you, until you sign out" over "Refresh every" (seconds).
- Rich-text editor: Editor version [Segmented control and status] Latest, Previous; the five tool switches [Fields, switch] as "Font", "Font size", "Font style", "Text colour button", "Background colour button"; "Spell checker: <engine>" as a meta line.
- Private label branding: the switch and its line; under it, always drawn, Logo address, Panel stylesheet address, then "Footer links" [Rows and gathered rows]: one row a link (its label, its address as meta), a row menu [Menus] Edit, Move up, Move down, Remove; "Add a link" quiet while fewer than five; Edit and Add open a drawer [Drawer] with Label and Address, its foot button the pane's Save.
- Under 576 px: rows full width, the drawer full width. Foot: Save the fill, one only.

## Which words?
- "Panel look", "Panel CSS", "Transparent acrylic effect", "Only for you, until you sign out", "Refresh every", "Footer links", "Add a link"; sentence case; no name starts with "Enable".

## What must be true?
- Every field posts under today's name; `appearance.save.php` stores what it stored; a removed link moves the ones under it up at the Save.
- `#settings/appearance/pgset-theme`, `…/pgset-dashboard`, `…/pgset-editor`, `…/pgset-private-label` open their rows; `custom_css` opens "Panel look".
- With Private label off its fields are drawn; a save still writes them only for an administrator.
- The settings search finds "custom css", "acrylic", "refresh" and "footer links".

Related: docs/memory/plans/general-appearance-settings-tidy.md, docs/memory/plans/general-appearance-settings-tidy-steps.md, docs/memory/plans/general-appearance-settings-tidy-screens.md
