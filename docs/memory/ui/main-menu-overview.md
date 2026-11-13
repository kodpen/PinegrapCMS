---
name: main-menu-overview
description: The panel's main menu as a whole: rows in categories, panels and second-level panels, states and the drawer, gates, the decisions that bind it, and which main-menu file holds each part
---

## What is it?
- The left menu of every panel screen, built by `output_menu()` (`includes/fn/output.php`) as rows (`$menu_items[<slot>]`), each under its gate, drawn under category headings (Content, Design, Ecommerce, Accounting, Marketing, System). A row is a link, or opens a panel of entries in place of the rows; an entry may open a second-level panel (System tools' groups, the Page Design Studio's Other).
- `backend.src.js` drives it: panels, the kept panel, the current entry, the three wide states, the phone drawer. Tests: `tests/menu_category_test.php`, `menu_panel_test.php`, `menu_state_test.php`.

## Which decisions bind it?
- Owner (2026-10-28, #2811, `workflow/decisions-made-panel.md`): categories as headings over rows, a heading only over rows the person gets; navigator screens keep the menu open or narrowed.
- Owner (2026-11-02, `decisions-made-panel-2.md`): no Site Settings row; panel entries' icons on two-coloured tiles.
- Owner (2026-11-12, #8849, `decisions-made-panel-6.md`): the legacy designer's entries and Ads sit under Page Design Studio, behind Other.
- Slots are never reused or renumbered: operators pin rows by slot. Retired: 3, 13, 16, 17, 18, 19, 20.

## Where is each part?
- Categories, slots, headings, retired rows: `main-menu-categories.md`.
- What a row's click does, the kept panel, the current entry and the row's hint, the widened rail: `main-menu-panels.md`; a panel a script fills, `window.pgMenu`, opening by itself: `main-menu-panel-scripts.md`; screens whose own navigation is a panel: `main-menu-navigator-screens.md`.
- Expanded, mini and hidden, the bar button, the cookie, Ctrl+D: `main-menu-states.md`; the phone drawer's focus: `main-menu-drawer-focus.md`.
- Who gets an entry: `main-menu-entry-gates.md`. Row drawings and entry icons: `main-menu-icons.md`; the Design Canvas's mark, drawn as the rows' icons: `design-canvas-icon.md`.
- Row 21 Page Design Studio and its Other: `main-menu-design-studio-row.md`; the retired Site Settings row and where Translations went: `main-menu-settings-row.md`; System tools: `settings-system-tools.md`.

## How is a new part added?
- A new screen: its entry goes into its row's `data-bs-content` under the screen's own gate, and its file name into `output_menu()`'s switch for the active row.
- A new row: the next free slot, a place in `pg_menu_categories()`, a drawing in `pg_menu_icon()`, a `[data-pg-menu-tone]` line for a new colour class.
- A second-level panel: an entry with a chevron and `data-pg-menu-panel` naming a panel drawn into `$menu_panels_nested` (`pg_menu_tools_markup()`, `pg_menu_other_markup()`); no script change is needed.

Related: docs/memory/ui/main-menu-categories.md, docs/memory/ui/main-menu-panels.md
