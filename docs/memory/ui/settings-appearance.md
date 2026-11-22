---
name: settings-appearance
description: Site Settings › Appearance as folded rows: the four rows and their state lines, what each row holds, who sees which row, what stayed (ids, field names, the save, Reset widgets)
module: appearance_settings_fold
---

## How is the pane drawn?
- Folded rows (`pg_settings_fold()`, `ui/settings-fold-rows.md`), first row open, in this order: Panel look, Dashboard, Rich-text editor, Private label branding. Order, names, icons and lines: `includes/fn/appearance_settings_fold.php` (`pg_appearance_fold_order()`, `pg_appearance_fold_face()`, `pg_appearance_fold_<part>()`, pure but for `lang()`).
- The purpose line is the category's description in `registry.php`: "How the panel looks for everyone who uses it."
- No `div.switch` and no `collapse-switcher`: every switch is `pg-switch`, its fields drawn under it whatever its state. Test: `tests/settings_appearance_fold_test.php` (renders the pane for a manager, and for an administrator in a child process).

## Who sees which row?
- A manager: Panel look and Rich-text editor. A designer adds Dashboard (`$dashboard_card_shown`, `pg_dashboard_appearance_allowed()`); only an administrator has Private label branding. The save checks the same gates.

## What does each row say and hold?
- Panel look (`pgset-theme`): line "Acrylic on, panel CSS set" (off / no panel CSS). "Transparent acrylic effect" (`advanced_visual_effects`) with "For everyone who uses the panel…"; "Panel CSS" (`custom_css`, once "Custom CSS"), its line "Styles the control panel only, never the site" and, for an administrator, that a whole stylesheet is set under Private label branding.
- Dashboard (`pgset-dashboard`): line "Glass cards · refresh every 60 seconds". Group "For everyone": Card style, Panel backdrop, "Reset widgets" (still the submit `dashboard_reset_widgets`). Group "Only for you, until you sign out": "Refresh every" (`dashboard_refresh`, kept in the session).
- Rich-text editor (`pgset-editor`): line "Latest editor" / "Previous editor". Editor version a `pg-seg` of two radios (`page_editor_version`), the five tool switches "Font", "Font size", "Font style", "Text colour button", "Background colour button" (`page_editor_font…` names unchanged), "Spell checker: <engine>" a meta line.
- Private label branding (`pgset-private-label`): line On / Off. "Use your own branding" (`private_label`), then always drawn "Logo address", "Panel stylesheet address" and "Footer links" as list rows, each edited in its drawer (`ui/settings-appearance-footer-links.md`).

## What stayed?
- Card ids, field names and `appearance.save.php`; the search words gained "panel css" and keep "custom css".

Related: docs/memory/ui/settings-fold-rows.md, docs/memory/ui/settings-dashboard-card.md, docs/memory/plans/general-appearance-settings-tidy.md
