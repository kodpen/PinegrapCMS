---
name: explorer-menu-tree
description: Plan: the File Manager and Catalog Manager trees in their main menu panels like the Workspace list, and the menu shown open on the workspace, file manager and catalog screens
---

## What did the owner ask for? (2026-10-28)
- The explorer's left tree (`#explorer_tree_pane`, `view_folders.php`; the catalog screen is the same file with `$explorer_area = 'catalog'`) goes into the File Manager (slot 1) and Catalog Manager (slot 12) panels, the way the Workspace list did (#2545): on every panel page, links loaded on the first open, in place on its own screen. The "Folder tree" toggle goes.
- Workspace, File Manager and Catalog screens: the menu comes open at lg and up even if the saved state is mini or hidden. The button only narrows it to the mini rail, never hides it, and a narrowed menu stays narrow there (its own cookie, e.g. `softwaremenunav` = expanded | collapsed, default expanded); the other screens keep `softwaremenustatus`. Phones: the drawer, shut, as everywhere.
- No duplicate rows: the File Manager panel drops its fixed Files and Pictures entries (the tree has Files, Pictures, Pages); keep File Manager, Create Folder, Upload Files or Folders, Create File above the tree.
- One PR, a commit per step; branch `feature/explorer-menu-tree` from development (after #2811).

## Steps

1. Menu state: `output_header()` builds `output_menu()` before the mode button; a static flag (`pg_menu_navigator()`) set where a panel prints `data-pg-menu-auto` (Workspace screens, the explorer's own screen) picks the shown state from the navigator cookie; `data-pg-menu-navigator` on `.software-container`. `backend.src.js`: read it above the init calls (`syncMenuMode()` runs inside them), use it in the wide branch, the button toggles expanded/collapsed and writes only the navigator cookie. Tests in `tests/menu_state_test.php`.

2. Read-only action `explorer_side` (`includes/panel/actions.php`, token, write false; handler file `includes/panel/explorer_side.php`, pure helpers in a new `includes/fn/explorer_side.php`): `global $user, $folders_that_user_has_access_to`, validate_user then validate_token, area files (role < 3 or `no_acl_check()`) or catalog (`ECOMMERCE` and `pg_catalog_access()`), rows only from `pg_explorer_child_folders()` / `pg_catalog_child_groups()`, int node ids capped, session released, no-store; never touches `$_SESSION['software']['explorer']`.

3. Links-only renderer `assets/js/explorer_side.js` (own bundle) in both panels on other pages: server-printed quick links under the screen's gates (Short Links, Shared, Backups role <= 2; ERP Files per `view_folders.php:51`), lazy branches, `data-pg-menu-skip` on tree links, per-area keys `pg_explorer_tree_<area>`, Pinned/Recent from `pg_explorer_pinned_<area>` / `pg_explorer_recent_<area>`. CSS: add the box to the `.bi` 1.3rem `:where()` exclusion, `overflow-x: auto`.

4. On its own screen the pane moves whole into an in-place box (`data-pg-menu-auto`) by a short script after the pane: keep every id (unguarded lookups throw otherwise); re-scope the area CSS (`view_folders.php` 916-917) and drop the phone hide (1411-1412); remove `#tree_toggle_button`, its half of `syncPaneButtons()` and the boot `applyPaneState()` for the tree; `data-pg-menu-hold` on `#explorer_menu`; explorer keydown returns inside `#menu`; `pgMenu.dismiss()` after a tree navigation; tour copy via `tools/tr_add.php` and keys `explorer_files.4` / `explorer_catalog.4`.

## Where to start?
- Step 1, `includes/fn/output.php` around `$menu_state = pg_menu_state(` and `backend.src.js` around `MENU_PANEL_KEY` / `savedMenuState()`. Afterwards record the owner's rule in `workflow/decisions-made-panel.md` and rewrite `decisions-made-workspace.md` ("on the mini rail a click widens the rail"), `ui/main-menu-states.md`, `ui/main-menu-panel-scripts.md`, `workspace/workspace-menu-list.md`.
