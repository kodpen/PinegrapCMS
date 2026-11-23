---
name: look-design-examples
description: The Design examples screen (design_examples.php, DL2 #8907): every look A recipe live in a light and a dark pane, the classes under each, what is not a recipe yet; where a step copies markup from
module: look_examples
---

## What is the Design examples screen?
- `design_examples.php`, for the Designer role and above (`validate_area_access($user, 'designer')`), linked from System tools › Jobs for role ≤ 1 (`pg_settings_tool_groups()`, search words in `pg_settings_tool_keys()`). Help goes to `site-settings#system-tools`.
- It draws every recipe of `look-type-and-recipes.md` twice, in a pane with `data-bs-theme="light"` and one with `data-bs-theme="dark"`, so both themes show at once whatever the reader's own theme. The panel's tokens and the recipes' tint strengths flip on `[data-bs-theme]`, so a subtree theme works.

## Where does the markup live?
- `includes/fn/look_examples.php`, loaded only by the screen: `pg_look_examples($prefix)` gives the sections (type, buttons, segmented control and status, icon tiles and people, fields, cards, rows and gathered rows, drawer, menus), each with its title, its classes and its live markup; `$prefix` keeps the field ids of the two panes apart. `pg_look_examples_pane($theme)` draws one pane; `pg_look_examples_not_done()` the two lists at the top.
- Buttons include the split button (`.pg-button-split`, `.pg-button-split-more`: the main action and a chevron flush on its right, outer corners rounded, a hairline between; #10858).
- A screen step copies its markup from a section; the look itself stays in the recipe block of `backend.src.css`. The screen's own classes (`.pg-look-*`, before the `.alert p.notice` rules) only place the examples.

## What does the top card say is not done?
- Drawn in the sheet, not a recipe yet: figure cards with a trend line, the 12-column field grid (DL5c), the bell row's avatar and verb.
- Not drawn yet: dialogs, dashboard cards, report screens, Workspace (channel screen). The settings modal's pane and phone widths left the list with DX4 (the settings pane pattern and the phone frame).
- The list table is drawn by the driver itself (`list-table-layout.md`, #8921), so it left the list.
- A new recipe adds its section here in the same PR; a part that is done leaves the list.
- Drawer (#10851): drawn in place without the dialog, as `#privacy_request` and Application Access' `#api_new` build it; the Bootstrap classes are named without a dot (they are not rules of `backend.src.css`). The numbered steps come with KV4b.
- Menus (SF2, #10852): a row's "more" menu, `pg-icon-button-sm` opening a Bootstrap `.menu.pg-menu-end` in `.pg-menu-wrap`; a danger item `fg-emphasis-danger` last under `.menu-divider`. No new CSS (architect, 2026-11-20, channel message 11049).
- Patterns (`pg_look_examples_patterns()`, under the parts): which there are, in which order, and their frame: `look-design-examples-patterns.md`.
- Phone (DX4): under the two panes a 390 × 844 px `iframe.pg-look-phone-frame` of `design_examples.php?phone=1`, the same screen behind the same gate drawing only `pg_look_examples_phone_pane()` with `hide_menu`. A frame, not a narrow box: the panel's phone rules are `@media` queries on the window.

## What keeps it honest?
- `tests/look_examples_test.php`: every class a section names is a rule of `backend.src.css` and is drawn in that section's markup; no id twice on the page; the gate and the System tools link.

Related: docs/memory/ui/look-design-examples-patterns.md
