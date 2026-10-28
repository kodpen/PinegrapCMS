---
name: main-menu-panels
description: Main menu rows that open their entries in a panel inside the menu: which rows, where entries and permissions come from, the widened rail, the kept panel, the current entry
---

## What does a click on a menu row do?
- A row with more than one entry, a control, or one entry that goes elsewhere opens a panel in place of the rows: back arrow, row name, entries (`pg_menu_row_opens_panel()`, `pg_menu_panel()`, `includes/fn/output.php`; `tests/menu_panel_test.php`). The arrow or Escape brings the rows back.
- A row with no entries, or one that is its own screen, stays a plain link (Dashboard).
- An entry can open a panel of its own (Site Settings, `main-menu-settings-row.md`); the back arrow and Escape go up one level (`backMenuPanel()`).
- The pinned My Notes row has My Notes and Add Note (`?new=1`). Its panel (`menu_panel_24`) is drawn whenever the notes are open to the person, pinned or not, so `workspace.js` `drawMenuRow()` hooks a row pinned at runtime to it.
- The row keeps its `href`: middle click, Ctrl/Cmd click or "open in new tab" opens its screen; the right button gets the browser's menu.

## Where do the entries and their permissions come from?
- The panel body is the row's `data-bs-content` string, built in `output_menu()` under each entry's own gate (role, `USER_MANAGE_*`, ERP, workspace rights, module switches), drawn as it is. A new entry goes into that string under the gate of its screen (`main-menu-entry-gates.md`).
- The Workspace row (slot 23) holds an empty box its script fills with the Workspace list (`main-menu-panel-scripts.md`, `workspace/workspace-menu-list.md`).

## What happens on the collapsed rail?
- A row that opens a panel has a small arrow on the rail too. No room for a panel, so the click widens the rail over the content to `--pg-rail-w-expanded` (`.pg-menu-peek` on `.software-container`); the grid column stays the rail's, nothing under it moves. The back arrow, Escape, a click outside the sidebar or focus leaving it put the rail back. Changing the menu state settles it (`settleMenuPanel()`).
- Focus going into a frame (rich text editor) puts it back too: `focusout` without `relatedTarget` and `window` `blur` look for the frame as the active element on the next turn.
- In the phone drawer the panel opens in the drawer; the first Escape goes back wherever the focus is, the next closes the drawer.

## Does the panel stay open across pages?
- While the labels show (expanded, drawer) the open panel is kept for the tab (`sessionStorage` `pgMenuPanel`). The next page reopens it, without the slide, only when one of its entries is the page or its row is the page's row; otherwise it is forgotten, and a panel marked to open by itself there opens (`data-pg-menu-auto`). The rail never reopens one.

## How are the current entry and the row's hint found?
- `markMenuCurrent()` (`backend.src.js`) marks in each panel the entry whose path is the page's, whose query parameters are all on the page (more wins, the same set best) and whose `#section`, if any, is the page's: `.pg-menu-current`, `aria-current="page"`, coloured through `--bs-emphasis-color-rgb` (`.link-body-emphasis` is `!important`). Its name goes into the row's `.pg-menu-hint` (unless it is the row's own name); CSS shows the hint on the `.active` row only, so it follows `.active` when `workspace.js` pins or unpins My Notes.
- `MENU_PANEL_KEY` is assigned above the init calls; below them it was undefined at load.

## What else had to change with it?
- The loading curtain skips `[data-pg-menu-panel]` clicks (`pg_preloader_markup()`); the drawer's close-on-link handler ignores them.
- Rows carry `no-popover`, so `pgBindTitlePopovers()` leaves them the browser's title hint.
- The panel CSS outweighs `#menu .menu-body .bi { font-size: 1.3rem }` (entries carry `bi`). The head is a tinted bar with a smaller title, so long names keep to one line.

Related: docs/memory/ui/main-menu-states.md, docs/memory/ui/loading-curtain.md
