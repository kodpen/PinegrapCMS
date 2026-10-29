---
name: explorer-menu-tree
description: The File Manager's and the Catalog Manager's tree in their main menu panels: moved in on the explorer's own screen, links elsewhere (explorer_side.js, the explorer_side action), gates, per-area keys
module: explorer_side
---

## Where is the explorer's tree?
- In the main menu: the File Manager panel (slot 1) and the Catalog Manager panel (slot 12), under each row's gate, printed by `pg_menu_explorer_side()` (`includes/fn/output.php`) through `pg_explorer_side_markup()` (`includes/fn/explorer_side.php`). The explorer screens have no tree column and no "Folder tree" switch any more. Owner decision: `workflow/decisions-made-panel.md`.
- On the area's own screen (`pg_explorer_side_screen()`: `view_folders.php` files, `view_product_groups.php` catalog) the box is empty, `data-explorer-side-inplace data-pg-menu-auto`, and raises `pg_menu_navigator()`; a short script right after `#explorer_tree_pane` moves the pane whole into it before the explorer starts, so every id, click, the tree menu, drops, long press, Pinned/Recent and the tour keep working. Without the box (a screen that hides the menu) the pane stays in the screen.
- Anywhere else the box holds the fixed entries as links and an empty tree `assets/js/explorer_side.js` (own bundle) fills from the `explorer_side` action on the first `pg:menu-panel-open`, then branch by branch (a button per arrow). Rows are links to `?folder=N` / `?group=N`, all `data-pg-menu-skip`.

## Who gets what?
- `explorer_side` (`includes/panel/explorer_side.php`, exempt, token, read only): validate_user, validate_token, then `pg_explorer_side_allowed()` (files: role < 3 or `no_acl_check()`; catalog: `ECOMMERCE` and the commerce right), refused in JSON. Folders only from `pg_explorer_child_folders()` (role 3: its granted forest, the bin left out), groups only from `pg_catalog_child_groups()`; at most 25 whole-number node ids; session released; `no-store`; the explorer's place in the session untouched. Answer `nodes: [{id, rows}]`.
- The fixed entries follow the screen's gates (`pg_explorer_side_quick()`): Short Links, Shared Folders, Backups for role <= 2; ERP Files where `view_folders.php` draws its ERP section. The script draws names with `textContent`.
- Pinned and Recent live in the browser (another person's, or from before a right was taken away), so both renderers send their ids as `shortcut_ids` (at most 50) and draw only the `shortcuts` the action returns (visible folder, not the bin; live group), under today's names; the explorer screen's `verifyShortcuts()` does it at boot, a place the page itself showed (visited, pinned) counting as confirmed even before the answer. Shown errors are only the server's own words; a branch shows Loading and Try again; many open branches go in requests of 25.

## What changed on the explorer screen?
- The area CSS reads `:is(#explorer_app, #explorer_tree_pane)[data-area]`; the phone rule hides only `#explorer_main > #explorer_tree_pane`, so the drawer holds the tree.
- Gone: `#tree_toggle_button`, its half of `syncPaneButtons()`, `pg_explorer_tree_open`. Open branches are kept per area (`pg_explorer_tree_files` / `_catalog`, read by both renderers; the files tree reads the old shared key once).
- `#explorer_menu` has `data-pg-menu-hold` (the widened rail and the phone drawer stay while it is used); a navigating click in the tree and its menu's Open call `treeMakeWay()` (`pgMenu.dismiss()`); long press builds its node with `treeNodeFromRow()`; keys inside `#menu` are not the listing's. Tour keys `explorer_files.4` / `explorer_catalog.4`; the tree steps skip a tree off the screen (shut drawer, rail).
- The panels keep File Manager (skip), Create Folder, Upload, Create File / Catalog Manager (skip), Create Product Group, Featured & New above the tree; the tree marks the open folder or view.
