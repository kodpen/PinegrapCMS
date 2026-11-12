---
name: look-design-examples
description: The Design examples screen (design_examples.php, DL2 #8907): every look A recipe live in a light and a dark pane, the classes under each, what is not a recipe yet; where a step copies markup from
module: look_examples
---

## What is the Design examples screen?
- `design_examples.php`, for the Designer role and above (`validate_area_access($user, 'designer')`), linked from System tools › Jobs only for role ≤ 1 (`pg_settings_tool_groups()`, search words in `pg_settings_tool_keys()`). Help goes to `site-settings#system-tools`.
- It draws every recipe of `look-type-and-recipes.md` twice, in a pane with `data-bs-theme="light"` and one with `data-bs-theme="dark"`, so both themes show at once whatever the reader's own theme. The panel's tokens and the recipes' tint strengths flip on `[data-bs-theme]`, so a subtree theme works.

## Where does the markup live?
- `includes/fn/look_examples.php`, loaded only by the screen: `pg_look_examples($prefix)` gives the sections (type, buttons, segmented control and status, icon tiles and people, fields, cards, rows and gathered rows), each with its title, its classes and its live markup; `$prefix` keeps the field ids of the two panes apart. `pg_look_examples_pane($theme)` draws one pane; `pg_look_examples_not_done()` the two lists at the top.
- A screen step copies its markup from a section; the look itself stays in the recipe block of `backend.src.css`. The screen's own classes (`.pg-look-*`, before the `.alert p.notice` rules) only place the examples.

## What does the top card say is not done?
- Drawn in the sheet, not a recipe yet: figure cards with a trend line, the list table (DL5b), the 12-column field grid (DL5c), the bell row's avatar and verb.
- Not drawn yet: dialogs and drawers (the settings modal), dashboard cards, report screens, Workspace, phone widths.
- A new recipe adds its section here in the same PR; a part that is done leaves the list.

## What keeps it honest?
- `tests/look_examples_test.php`: every class a section names is a rule of `backend.src.css` and is drawn in that section's markup; no id twice on the page; the gate and the System tools link.
