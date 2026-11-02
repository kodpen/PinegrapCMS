---
name: workspace-menu-list
description: The Workspace list in the main menu (#2545, layout #3794, #3817): workspace_side.js draws ws_side_payload() on every panel page; its order, who sees what, inline data vs ws_side, hooks, pill, tour
---

## Where is the list, and who sees it?
- In the main menu's Workspace panel (slot 23 of `output_menu()`), the only list of channels, discussions, groups and screens; no `.ws-side` column or icon rail elsewhere (`workflow/decisions-made-workspace.md`).
- `assets/js/workspace_side.js` draws into `#ws-menu-side` (`ws_side_markup()`, `includes/workspace/nav.php`); the `ws-` id brings the `--ws-*` tokens to any page.
- Printed only inside slot 23's gate (`ws_rights()['member']`). The data comes only from `ws_channels_for()`, `ws_groups_for()`, `ws_threads_for()`, `ws_inbox_unread_count()` and `ws_nav_groups()` (each place under its gate); the script hides nothing. `ws_side_payload()` is `array()` for a non-member.

## What is its order, top to bottom?
- #3794 (owner: `workflow/decisions-made-workspace-5.md`): the search alone on its row ("Ctrl K" where it opens the palette); "+", the gear (`ws_nav_groups()['head']`) and the tour button `[data-ws-tour]` in the panel's head (`.ws-side-head-tools`, #3922: they squeezed the field); places without heading (`top`: Today, Overview until #4196, Inbox opening the bell with the row's count, My Tasks: `ui/notifications-one-inbox.md`); one Channels section (pinned, group tree, other joined, not joined dimmed; heading key `channels`, a channel dropped on it leaves its group); My discussions (`th_mine`); Tools tiles and More (`workspace-menu-tools.md`).
- Mention counts in `--ws-accent`, unread neutral.

## When is the data read?
- Workspace screens (`ws_side_screen()`): with the page (`#ws-side-data`); they are navigator screens, so the menu is open (or the rail, never hidden; `ui/main-menu-navigator-screens.md`) and the panel opens by itself. `workspace.js` asks `ws_bootstrap` with `lean`, so `ws_threads_for()` runs once.
- Elsewhere: the first `pg:menu-panel-open` posts `ws_side` (api.php, `pg_panel_ws`, token): `Cache-Control: private, no-store`, read only, no `ws_sync_seen()` (pushes are not held back). Once per page view; a failure offers Try again. Toolbar frame links target `_parent`.
- `tests/workspace_side_test.php`: gates, inline JSON escaping, no list query in slot 23 outside the screen branch, ws_side.

## What does a click do?
- Every entry is an `<a href>` (`?channel=N`, `?channel=P&thread=T`, the `ws_nav_groups()` url), so a new tab works. The channel screen hands in `app.sideHooks()`: channels, discussions, overview, inbox, scheduled, archived, audit open in place (`data-pg-no-curtain`), plus pin, drag, right-click menus, new group, the tour button and the drawn empty state. The other workspace screens hand in the inbox (`start()`), used only where a page has no bell; their `search` hook is no longer called.
- Elsewhere the search field goes to `?view=home&palette=1` (titled without Ctrl+K, which is the panel's search there), "+" to `?view=home&new=channel`; `app.start()` answers each once and drops it from the address.
- Closed sections (`channels`, `threads`, `tools`) and groups stay `ws-side-closed` / `ws-groups-closed` (localStorage) on every page; a closed section still shows the open channel or screen. `saveOrder()` reads `.ws-sortable [data-ws-channel]` in page order (pinned, groups, mine). On the channel screen the row's count follows `BOOT.inbox`.

## What else changed with it?
- The Channels pill (`channelsToggle()`) shows where the list is out of sight (below lg, the narrowed rail) and calls `PGWsSide.reveal()` (`ui/main-menu-panel-scripts.md`).
- Tour `workspace.3`: targets `#ws-menu-side …` or `.ws-side-head-tools …` (`inMenuList()`); a list step widens the rail and scrolls the entry to the middle, a screen step and the end put it back; `ready()` waits for the tour button.
- My Notes and Calendar subscription only where their tables are; staff keep Scheduled messages once Flows lists their actions.
