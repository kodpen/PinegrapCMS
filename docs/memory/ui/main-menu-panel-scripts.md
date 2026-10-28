---
name: main-menu-panel-scripts
description: A main menu panel a script fills (the Workspace list, the explorer tree): panel flag, pg:menu-panel-open, opening by itself, window.pgMenu, layers that keep the widened rail open, labels, icon size
---

## How does a row get a panel a script fills?
- `'panel' => true` on the row: `pg_menu_row_has_panel()` makes it a panel though its entries hold no link yet (`pg_menu_row_opens_panel()` counts links).
- `openMenuPanel()` dispatches `pg:menu-panel-open` (bubbling) on the panel: the row's click, a kept panel reopened, `pgMenu.openPanel()`.

## When does a panel open by itself?
- A panel holding `[data-pg-menu-auto]` opens at load when no kept panel was reopened, and again from `settleMenuPanel()` when the menu comes to show its labels (a state change, a breakpoint crossed), unless another panel is open; never on the rail (`autoMenuPanel()`, after `menuPanelsReady`). The Workspace list prints it on the workspace's own screens (`workspace/workspace-menu-list.md`), the explorer's box on its own screen (`explorer-menu-tree.md`): only the screen's own panel may carry it, since it also makes the screen a navigator, open unless narrowed (`main-menu-navigator-screens.md`).

## What is window.pgMenu?
- `openPanel(id, {focus})`: below lg the drawer opens on it, on the rail or a hidden menu the rail widens over the screen. Deferred with `setTimeout(0)`: asked from a click on the page, that click would reach the closers (the outside click, the tap behind the drawer) and shut it at once (#2545, the Channels pill).
- `dismiss()` closes the widened rail and the drawer; `refreshCurrent()` runs `markMenuCurrent()` again after `history.replaceState()`; `isRail()`.
- A hidden menu with `.pg-menu-peek` slides in over the screen (`.software-container.menu-hidden.pg-menu-peek .software-sidebar`), without the slide under reduced motion. The cookie is not touched.

## What keeps the widened rail open?
- `insideMenu()`: the sidebar, or a node in `[data-pg-menu-hold]` (workspace.js `ctxMenu()` and `floatBox()`, the tour's veil and bubble in `pg_tour.js`, the explorer's `#explorer_menu`), for the outside click, `focusout` and the frame check. The outside click also reads `composedPath()`, so a toggle that redrew itself still counts as inside.
- The `#menu` Escape returns when `defaultPrevented`: a search box in a panel clears itself first.

## Which entries mark the current page?
- `markMenuCurrent()` reads `a[href]` at any depth except `[data-pg-menu-skip]` (actions such as New channel); the row's hint takes `data-pg-menu-label` when set. The entry rules (`.pg-menu-panel-body > a`) style direct children only; a list in a wrapper styles itself.
- `#menu .menu-body .bi:not(:where(#ws-menu-side *, .pg-explorer-side *))`: `:where()` keeps the 1.3rem rule at (1,2,0), so panel entries, chevrons and the back icon keep their sizes.
- `.menu-body` drops its 100px bottom room while the Workspace list is open (`:has()`), so the list's sticky foot sits at the bottom edge (Chromium insets sticky boxes by the scroller's padding).
