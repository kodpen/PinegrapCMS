---
name: look-design-examples
description: The Design examples screen (design_examples.php, DL2 #8907): every look A recipe live in a light and a dark pane, the classes under each, what is not a recipe yet; where a step copies markup from
module: look_examples
---

## What is the Design examples screen?
- `design_examples.php`, for the Designer role and above (`validate_area_access($user, 'designer')`), linked from System tools › Jobs only for role ≤ 1 (`pg_settings_tool_groups()`, search words in `pg_settings_tool_keys()`). Help goes to `site-settings#system-tools`.
- It draws every recipe of `look-type-and-recipes.md` twice, in a pane with `data-bs-theme="light"` and one with `data-bs-theme="dark"`, so both themes show at once whatever the reader's own theme. The panel's tokens and the recipes' tint strengths flip on `[data-bs-theme]`, so a subtree theme works.

## Where does the markup live?
- `includes/fn/look_examples.php`, loaded only by the screen: `pg_look_examples($prefix)` gives the sections (type, buttons, segmented control and status, icon tiles and people, fields, cards, rows and gathered rows, drawer, menus), each with its title, its classes and its live markup; `$prefix` keeps the field ids of the two panes apart. `pg_look_examples_pane($theme)` draws one pane; `pg_look_examples_not_done()` the two lists at the top.
- Buttons include the split button (`.pg-button-split`, `.pg-button-split-more`: the main action and a chevron flush on its right, outer corners rounded, a hairline between; #10858).
- A screen step copies its markup from a section; the look itself stays in the recipe block of `backend.src.css`. The screen's own classes (`.pg-look-*`, before the `.alert p.notice` rules) only place the examples.

## What does the top card say is not done?
- Drawn in the sheet, not a recipe yet: figure cards with a trend line, the 12-column field grid (DL5c), the bell row's avatar and verb.
- Not drawn yet: dialogs and the settings modal, dashboard cards, report screens, Workspace, phone widths.
- The list table is drawn by the driver itself (`list-table-layout.md`, #8921), so it left the list.
- A new recipe adds its section here in the same PR; a part that is done leaves the list.
- Drawer (#10851): drawn in place without the dialog, as `#privacy_request` and Application Access' `#api_new` build it; the Bootstrap classes are named without a dot (they are not rules of `backend.src.css`). DX3 adds to this section; the numbered steps come with KV4b.
- Menus (SF2, #10852): a row's "more" menu, `pg-icon-button-sm` opening a Bootstrap `.menu.pg-menu-end` in `.pg-menu-wrap`; a danger item `fg-emphasis-danger` last under `.menu-divider`. No new CSS (architect, 2026-11-20, channel message 11049).
- Patterns (`pg_look_examples_patterns()`, under the parts): the settings pane, the connect row, a list's empty state (DX1 #10859); the starting-point sheet in its three steps and the numbered steps drawer (DX2 #10885), each in the Drawer section's standing frame.
- The Workspace's conversation timeline (#11047, `pg_look_examples_timeline_pattern()`), before the e-mail parts: the well and a still card, drawn in place by `.pg-look-example .ws-ctl-card`.
- "Flow box" (#11083, `pg_look_examples_flow_box_pattern()`) after the timeline: `workspace/workspace-flows-box-look.md`.
- The e-mail settings drawing's seven parts close the group (DX2b #10930, `pg_look_examples_mail_patterns()`): `look-mail-patterns.md`.
- The patterns' frame is no `.drawer`, which sets `--bs-drawer-padding-*`: its head and body take `p-5` (16 px, measured on the real `#api_new`; the sheet's head `p-5 pb-1`), as the Drawer section does since DX2. The sheet's cards use the recipe "Look A: starting-point cards" (`.pg-start*`); the steps drawer draws Claude's own guide (`ws_claude_setup_guide()`) with its tile, line and Close, as the real drawer.

## What keeps it honest?
- `tests/look_examples_test.php`: every class a section names is a rule of `backend.src.css` and is drawn in that section's markup; no id twice on the page; the gate and the System tools link.
