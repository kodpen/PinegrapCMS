---
name: main-menu-navigator-screens
description: Screens whose own navigation is a main menu panel (workspace, File Manager, Catalog Manager): the menu open or narrowed, never hidden, softwaremenunav, pg_menu_navigator()
---

## What is a navigator screen?
- A screen whose own way around is a menu panel that opens by itself: the workspace screens, the File Manager, the Catalog Manager (`pg_menu_navigator()`, raised by `output_menu()` where it prints `data-pg-menu-auto`). There the menu is open at lg and up, or narrowed to the rail, never hidden, from its own cookie `softwaremenunav` (expanded | collapsed, default open; `pg_menu_navigator_state()`). `output_header()` builds the menu before the button and marks `.software-container[data-pg-menu-navigator]`; `backend.src.js` reads it above the init calls (`MENU_NAVIGATOR`), and the button and Ctrl+D move it open/rail, writing only that cookie (`nextMenuState()`). The other screens keep `softwaremenustatus`; phones keep the shut drawer. Owner decision: `workflow/decisions-made-panel.md`.

## Why its own cookie?
- So narrowing the menu on the explorer does not change how every other screen opens, and the hidden state saved elsewhere does not take the tree away. Before (#2545) the workspace screens followed `softwaremenustatus` and opened their panel only while the labels showed.

Related: docs/memory/ui/main-menu-states.md, docs/memory/ui/explorer-menu-tree.md, docs/memory/workspace/workspace-menu-list.md
