---
name: main-menu-panels
description: Main menu rows that open their entries in a panel inside the menu: which rows, where entries and permissions come from, the widened rail, the kept panel, the current entry
---

## What does a click on a menu row do?
- A row with more than one entry, a control (the dashboard's widget settings) or one entry that goes somewhere else opens a panel inside the menu in place of the rows: a back arrow, the row's name, the entries (`pg_menu_row_opens_panel()`, `pg_menu_panel()`, `includes/fn/output.php`; `tests/menu_panel_test.php`). The arrow or Escape brings the rows back.
- A row whose only entry is its own screen (Forms; Dashboard for a basic user) stays a plain link.
- The row keeps its `href`: a middle click, Ctrl/Cmd click or "open in new tab" still opens its screen. The right mouse button gets the browser's own menu.

## Where do the entries and their permissions come from?
- The panel body is the row's `data-bs-content` string, built in `output_menu()` under each entry's own gate (role, `USER_MANAGE_*`, ERP, workspace rights, module switches), drawn as it is. No gate is repeated or moved: a new entry goes into that string under its check, as before.
- When this replaced the right-click menu, every row and entry was compared old against new for admin, designer, manager and seven basic users with different rights on five screens: the same rows, links, texts, icons, targets and controls.

## What happens on the collapsed rail?
- No room for a panel, so the click widens the rail over the content to `--pg-rail-w-expanded` (`.pg-menu-peek` on `.software-container`); the grid column stays the rail's, nothing under it moves. The back arrow, Escape, a click outside the sidebar or focus leaving it put the rail back. Changing the menu state settles it (`settleMenuPanel()`).
- In the phone drawer the panel opens in the drawer; the first Escape goes back, the next closes the drawer.

## Does the panel stay open across pages?
- While the labels show (expanded, drawer) the open panel is kept for the tab in `sessionStorage` (`pgMenuPanel`). The next page opens it again, without the slide, only when it is about that page: one of its entries is the page, or its row is the page's row. Otherwise it is forgotten. The rail never reopens one by itself.

## How are the current entry and the row's hint found?
- `markMenuCurrent()` (`backend.src.js`) marks in each panel the entry whose path is the page's and whose query parameters are all on the page with the same values (more of them wins, the same set best): `.pg-menu-current`, `aria-current="page"`. The page's own row (`.active`, server-drawn) shows that entry's name in `.pg-menu-hint` unless it is the row's own name.

## What else had to change with it?
- The loading curtain skips clicks on `[data-pg-menu-panel]` rows (`pg_preloader_markup()`): they used to be skipped as `data-bs-toggle` links.
- The rows carry `no-popover`: without the context toggle `pgBindTitlePopovers()` would have made their titles hover popovers; the browser's title hint names the icon on the rail.
- The drawer's "close on any menu link" handler ignores panel rows.
- The panel CSS outweighs the menu's `#menu .menu-body .bi { font-size: 1.3rem }`, which would size the entries (they carry `bi`).
- The dashboard tip that said to right-click Panel now says to click it.

Related: docs/memory/ui/main-menu-states.md, docs/memory/ui/loading-curtain.md
