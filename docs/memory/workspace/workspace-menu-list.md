---
name: workspace-menu-list
description: The Workspace list in the main menu (#2545, #6281, redrawn #11079-#11141): workspace_side.js draws ws_side_payload(): order, the Notifications row, All / Unread, tools dock, who sees what, data, hooks
---

## Where is the list, and who sees it?
- In the main menu's Workspace panel (slot 23 of `output_menu()`), the only list of channels, discussions, groups and screens; no other column or rail (`workflow/decisions-made-workspace.md`).
- `assets/js/workspace_side.js` draws into `#ws-menu-side` (`ws_side_markup()`, `includes/workspace/nav.php`); the `ws-` id brings the `--ws-*` tokens to any page.
- Printed only inside slot 23's gate (`ws_rights()['member']`). The data comes only from `ws_channels_for()`, `ws_groups_for()`, `ws_threads_for()`, `ws_inbox_unread_count()`, `ws_tasks_due_counts()` and `ws_nav_groups()` (each under its gate); the script hides nothing. `ws_side_payload()` is `array()` for a non-member.

## What is its order, top to bottom?
- The head: back, "Workspace", the gear and the round "+" (`ws_nav_groups()['head']`, `.ws-side-head-tools`). The search field alone on its row ("Search or go to", "Ctrl K" where it opens the palette).
- "All" and "Unread" across the list (#11141, `workspace-menu-unread.md`), with "Mark all as read" and the keys Alt+Up / Alt+Down.
- Today, then "Notifications" (opens the bell: `ui/notifications-one-inbox.md`); Channels (pinned, a hairline, the group tree, the other joined, "N channels you could join", New group last for managers; a channel dropped on its heading leaves its group); My discussions (`th_mine`). This part scrolls.
- The tools dock fixed at the list's foot, never folded, My Tasks first, More as a card (#11101, `workspace-menu-tools.md`).
- A group: a folder row, its name as written, the chevron last, its channels on a thin line of its colour (`.ws-group-inner`). Rows, marks and section names: `workspace-menu-rows.md` (#11079).
- Owner (2026-11-03, #6281, `workflow/decisions-made-workspace-6.md`) set Today over the channels; owner (2026-11-22, `decisions-made-workspace-48.md`) the dock, Unread and the row marks; owner (2026-11-28, `decisions-made-panel-20.md`) the Notifications row.

## When is the data read?
- Workspace screens (`ws_side_screen()`): with the page (`#ws-side-data`); navigator screens, so the menu (or the rail) and the panel open by themselves (`ui/main-menu-navigator-screens.md`). `workspace.js` asks `ws_bootstrap` with `lean`, so `ws_threads_for()` runs once.
- Elsewhere: the first `pg:menu-panel-open` posts `ws_side` (api.php, `pg_panel_ws`, token): `no-store`, read only, no `ws_sync_seen()`. Once per page view; a failure offers Try again.
- `tests/workspace_side_test.php`: gates, JSON escaping, no list query in slot 23 outside the screen branch.

## What does a click do?
- Every entry is an `<a href>` (`?channel=N`, `?channel=P&thread=T`, the `ws_nav_groups()` url), so a new tab works. The channel screen hands in `app.sideHooks()`: channels, discussions, Today, scheduled, archived, audit open in place (`data-pg-no-curtain`), plus pin, drag, menus, join, reload and new group. The other screens hand in the search box only.
- Elsewhere the search field goes to `?view=home&palette=1`, "+" to `?view=home&new=channel`; `app.start()` answers each once and drops it from the address; `?view=home&inbox=1` (the Notifications row's address) opens the bell.
- Closed sections (`channels`, `threads`) and groups stay `ws-side-closed` / `ws-groups-closed` (localStorage) on every page; a closed section still shows the open channel. `saveOrder()` reads `.ws-sortable [data-ws-channel]` in page order. On the channel screen the Notifications count follows `BOOT.inbox`.

## What else goes with it?
- The Channels pill (`channelsToggle()`) shows where the list is out of sight (below lg, narrowed rail), calling `PGWsSide.reveal()` (`ui/main-menu-panel-scripts.md`).
- Tour `workspace.3`: a list step (`inMenuList()`) widens the rail and centres the entry; `ready()` waits for `PGWsSide.attached()`.
