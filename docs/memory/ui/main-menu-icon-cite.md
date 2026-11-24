---
name: main-menu-icon-cite
description: Places outside the main menu that stand for a menu row (header quick note, Workspace tools dock, dashboard card heads, My Notes empty screen) draw the row's icon at rest with pg_menu_icon_cite()
module: output
---

## Which places draw a menu row's icon?
- A place outside the main menu that stands for a menu row draws that row's drawing, not a `bi` glyph: `pg_menu_icon_cite($name, $class)` (`includes/fn/output.php`), `pg_menu_icon()` with the class `pg-mi-cite`, no tile, no margin.
- The header's Quick note button, its box's label and "My Notes" link (`ws_notes_quick_markup()`).
- The Workspace tools dock's Notes and Flows tiles: `ws_nav_groups()` `menu_icon`, sent as `svg` by `ws_side_nav()`, drawn by `navIcon()` in `workspace_side.js`.
- The dashboard cards Automation flows, My notes, Incoming e-mail, File Management, Calendars, Orders, Offers: `pg_dashboard_v2_card_menu_icons()`, sent as the card's `svg`, drawn in `pg_dash.js`.
- The My Notes empty screen: `CFG.menu_icons`, `emptyScreen({menuIcon})` in `workspace.js`.
- An icon of an action or an object stays `bi`: a note in a list, a task's notes, the palette's Notes kind, the notes tagging a record, the dashboard shortcuts (a pin stores its icon name).

## How is it drawn?
- `backend.src.css`, after the main menu moves: `svg.pg-mi-cite` is 1em square with the glyph's -0.125em baseline, so it takes the place's own font size and colour; filled parts and the Calendars day take `currentColor`; `.pg-mi-h` parts are unfilled; the live part (`[data-l]`, the flows dot) is not drawn. No move: the moves are scoped to `#menu` rows.
- The header control sets the cite to `--pg-tap-icon`, the size of the other controls' icons.
- A name without a drawing gives `''`, and each place falls back to its `bi` icon. `tests/menu_icon_cite_test.php`.

Related: docs/memory/ui/main-menu-icons.md, docs/memory/ui/main-menu-icon-motion.md
