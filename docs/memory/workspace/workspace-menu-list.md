---
name: workspace-menu-list
description: The Workspace list in the main menu (#2545, layout #3794, #3817, #6281): workspace_side.js draws ws_side_payload() on every panel page: order, who sees what, inline data vs ws_side, hooks, pill, tour
---

## Where is the list, and who sees it?
- In the main menu's Workspace panel (slot 23 of `output_menu()`), the only list of channels, discussions, groups and screens; no other column or rail (`workflow/decisions-made-workspace.md`).
- `assets/js/workspace_side.js` draws into `#ws-menu-side` (`ws_side_markup()`, `includes/workspace/nav.php`); the `ws-` id brings the `--ws-*` tokens to any page.
- Printed only inside slot 23's gate (`ws_rights()['member']`). The data comes only from `ws_channels_for()`, `ws_groups_for()`, `ws_threads_for()`, `ws_inbox_unread_count()` and `ws_nav_groups()` (each place under its gate); the script hides nothing. `ws_side_payload()` is `array()` for a non-member.

## What is its order, top to bottom?
- Owner #3794, #6281 (`workflow/decisions-made-workspace-6.md`): the search alone on its row ("Ctrl K" where it opens the palette); "+" and the gear (`ws_nav_groups()['head']`) in the panel's head (`.ws-side-head-tools`), 1.75rem squares: the solid "+" a circle, not the old 1.625×1.9rem oval; Today alone (`top`; no Inbox entry, the bell is the one inbox: `ui/notifications-one-inbox.md`); one Channels section (pinned, group tree, other joined, not joined dimmed, New group last for managers; heading key `channels`, a channel dropped on it leaves its group); My discussions (`th_mine`); Tools tiles, My Tasks first, and More (`workspace-menu-tools.md`).
- A group: a folder row, its name as written (no capitals), the chevron last (hover, closed), its channels on a thin line of its colour (`.ws-group-inner`).
- Mentions in `--ws-accent`, unread neutral.

## When is the data read?
- Workspace screens (`ws_side_screen()`): with the page (`#ws-side-data`); navigator screens, so the menu (or the rail, never hidden; `ui/main-menu-navigator-screens.md`) and the panel open by themselves. `workspace.js` asks `ws_bootstrap` with `lean`, so `ws_threads_for()` runs once.
- Elsewhere: the first `pg:menu-panel-open` posts `ws_side` (api.php, `pg_panel_ws`, token): `Cache-Control: private, no-store`, read only, no `ws_sync_seen()` (pushes are not held back). Once per page view; a failure offers Try again. Frame links target `_parent`.
- `tests/workspace_side_test.php`: gates, JSON escaping, no list query in slot 23 outside the screen branch.

## What does a click do?
- Every entry is an `<a href>` (`?channel=N`, `?channel=P&thread=T`, the `ws_nav_groups()` url), so a new tab works. The channel screen hands in `app.sideHooks()`: channels, discussions, Today, scheduled, archived, audit open in place (`data-pg-no-curtain`), plus pin, drag, right-click menus, new group and the drawn empty state. The other workspace screens hand in the search box only.
- Elsewhere the search field goes to `?view=home&palette=1` (titled without Ctrl+K, which is the panel's search there), "+" to `?view=home&new=channel`; `app.start()` answers each once and drops it from the address; the old Inbox address `?view=home&inbox=1` opens the bell.
- Closed sections (`channels`, `threads`, `tools`) and groups stay `ws-side-closed` / `ws-groups-closed` (localStorage) on every page; a closed section still shows the open channel or screen. `saveOrder()` reads `.ws-sortable [data-ws-channel]` in page order (pinned, groups, mine). On the channel screen the row's count follows `BOOT.inbox`.

## What else changed with it?
- The Channels pill (`channelsToggle()`) shows where the list is out of sight (below lg, narrowed rail), calling `PGWsSide.reveal()` (`ui/main-menu-panel-scripts.md`).
- Tour `workspace.3`: a list step (`inMenuList()`) widens the rail and centres the entry, a screen step and the end put it back; the bell step rings `#notification_toggle`, the last the header's tour button; `ready()` waits for `PGWsSide.attached()` and `.ws-center`.
