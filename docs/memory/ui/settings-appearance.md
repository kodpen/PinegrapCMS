---
name: settings-appearance
description: Site Settings › Appearance as folded rows: Panel look (acrylic, Panel CSS), Dashboard, Rich-text editor, Private label branding; who sees which row, what moved, what stayed
module: appearance_settings_fold
---

## How is the pane drawn?
- Folded rows since #11012 (`pg_settings_fold()`, `ui/settings-fold-rows.md`), first row open, in this order: Panel look, Dashboard, Rich-text editor, Private label branding. Order, names, icons and lines: `includes/fn/appearance_settings_fold.php` (`pg_appearance_fold_order()`, `pg_appearance_fold_face()`, `pg_appearance_fold_<part>()`, pure but for `lang()`).
- The purpose line is the category's description in `registry.php`: "How the panel looks for everyone who uses it." `pg_settings_categories()` still replaces it by role: a manager reads "The panel theme and the rich-text editor.", an administrator "The panel theme, the dashboard, the rich-text editor and private label branding."; only a designer sees the purpose line.
- No `div.switch` and no `collapse-switcher`: every switch is `pg-switch`, its fields drawn under it whatever its state. One Save, the pane's fill. Test: `tests/settings_appearance_fold_test.php` (a manager, and an administrator in a child process).

## Who sees which row?
- A manager: Panel look and Rich-text editor. A designer adds Dashboard (`pg_dashboard_appearance_allowed()`, `ui/settings-dashboard-card.md`); only an administrator has Private label branding. The save checks the same gates.

## What does each row say and hold?
- Panel look (`pgset-theme`): line "Acrylic on, panel CSS set" (off / no panel CSS). "Transparent acrylic effect" (`advanced_visual_effects`) with its line for everyone who uses the panel; "Panel CSS" (`custom_css`) in the code editor, its line "Styles the control panel only, never the site." (for an administrator also naming the Private label stylesheet).
- Dashboard (`pgset-dashboard`): line "Glass cards · refresh every 60 seconds". Group "For everyone": Card style, Panel backdrop, "Reset widgets". Group "Only for you, until you sign out": "Refresh every" (seconds).
- Rich-text editor (`pgset-editor`): line "Latest editor" / "Previous editor". Editor version a `pg-seg` Latest / Previous (`page_editor_version`), the five tool switches "Font", "Font size", "Font style", "Text colour button", "Background colour button", the spell checker engine a meta line.
- Private label branding (`pgset-private-label`): line On / Off. "Use your own branding" (`private_label`), then always drawn "Logo address", "Panel stylesheet address" and "Footer links" as list rows, each edited in its drawer (`ui/settings-appearance-footer-links.md`, #11092).

## What moved, and why?
- The four cards became rows; Theme is "Panel look".
- "Enable Transparent Acrylic Effect" spoke of the device in use, though it is site-wide: now "Transparent acrylic effect" with the line for everyone.
- "Custom CSS" styles the panel only (`includes/fn/output.php`) and Private label's stylesheet is a second panel stylesheet: relabelled "Panel CSS", its line names the other.
- The Dashboard card mixed site-wide Card Style and Panel Backdrop with Refresh Time, kept for this sign-in: now two groups saying whom each reaches.
- Editor Version was a radio pair, the spell checker a disabled box; Logo URL and Control Panel Stylesheet URL are "Logo address" and "Panel stylesheet address"; ten footer boxes are up to five rows.

## What stayed?
- Card ids, field names and `appearance.save.php`; `custom_css` opens Panel look. Reset widgets still posts `dashboard_reset_widgets`; Refresh every stays in the session (a per-person refresh column was left out).
- With Private label off its fields are drawn, and a save writes them only for an administrator.
- The search words gained "panel css", "acrylic", "footer links" and keep "custom css" (old labels stay searchable, `ui/settings-general.md`).

Related: docs/memory/ui/settings-fold-rows.md, docs/memory/ui/settings-dashboard-card.md, docs/memory/ui/settings-general.md
