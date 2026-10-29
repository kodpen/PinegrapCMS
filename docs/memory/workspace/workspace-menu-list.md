---
name: workspace-menu-list
description: The Workspace list in the main menu (#2545): workspace_side.js draws ws_side_payload() on every panel page; who sees what, inline data vs ws_side, the channel screen's hooks, pill, tour
---

## Where is the list, and who sees it?
- In the main menu's Workspace panel (slot 23 of `output_menu()`), the only list of channels, discussions, groups and workspace screens: the channel screen has no `.ws-side` column and the other screens no icon rail or drawer (`ws_screen_rail()` is gone). Owner decision: `workflow/decisions-made-workspace.md`.
- `assets/js/workspace_side.js` (bundle `workspace_side.min.js`) draws into `#ws-menu-side` (`ws_side_markup()`, `includes/workspace/nav.php`); the `ws-` id brings the `--ws-*` tokens to any page.
- Printed only inside slot 23's gate (`ws_rights()['member']`). The data comes only from `ws_channels_for()`, `ws_groups_for()`, `ws_threads_for()`, `ws_inbox_unread_count()` and `ws_nav_groups()` (each place under its screen's gate); the script hides nothing itself. `ws_side_payload()` is `array()` for a non-member.

## When is the data read?
- Workspace screens (`ws_side_screen()`): with the page (`#ws-side-data`); they are navigator screens, so the menu is open there (or narrowed to the rail, never hidden; `ui/main-menu-navigator-screens.md`) and the panel opens by itself. `workspace.js` then asks `ws_bootstrap` with `lean` and takes BOOT's channels, groups, threads and inbox from it, so `ws_threads_for()` (up to ~150 queries) runs once.
- Elsewhere: the first `pg:menu-panel-open` posts `ws_side` (api.php, `pg_panel_ws`, token): `Cache-Control: private, no-store`, read only, no `ws_sync_seen()` (pushes are not held back). Once per page view; a failure shows its message and Try again. In the toolbar frame links target `_parent`.
- `tests/workspace_side_test.php`: gates, escaping of the inline JSON, no list query in slot 23 outside the screen branch, the ws_side case.

## What does a click do?
- Every entry is an `<a href>` (`?channel=N`, `?channel=P&thread=T`, the `ws_nav_groups()` url), so a new tab works. The channel screen hands in `app.sideHooks()`: channels, discussions, overview, inbox, scheduled, archived, audit open in place (`data-pg-no-curtain`), plus pin, drag, right-click menus, new group, the tour button and the drawn empty state. The other workspace screens hand in the inbox and the search box (`start()`).
- Elsewhere the message search goes to `?view=home&search=`, "+" to `?view=home&new=channel`, the magnifier to `?view=home&palette=1` (titled without Ctrl+K, which is the panel's search there); `app.start()` answers each once and drops it from the address.
- Closed sections and groups stay `ws-side-closed` / `ws-groups-closed` (localStorage) on every page; a closed section still shows the open channel or screen. On the channel screen the row's count follows `BOOT.inbox`.

## What else changed with it?
- The Channels pill (`channelsToggle()`) shows where the list is out of sight (below lg, the narrowed rail) and calls `PGWsSide.reveal()` (`ui/main-menu-panel-scripts.md`).
- Tour `workspace.3`: targets `#ws-menu-side …`; a list step widens the rail and scrolls the entry to the middle, a screen step and the end put it back; `ready()` waits for the list's tour button.
- Calendar subscription and My MCP connections stay in Work and planning; My Notes and Calendar subscription only where their tables are; staff keep Scheduled messages once Flows lists their actions.
