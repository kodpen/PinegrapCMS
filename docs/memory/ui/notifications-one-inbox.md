---
name: notifications-one-inbox
description: The bell as the one inbox (#3817): filters in bell-filters.md, a flow's runs folded and drawn as gathered lines, pgBellOpen() for the Workspace list's Notifications row, the Workspace count
---

## Which filters does the bell have, and what does it keep?
- The selector All / Unread / Asked of me, the module chips, the search and the kept choice: `bell-filters.md`.

## How are a flow's runs folded?
- `ws_bell_fold_key()` (`includes/workspace/notify.php`): `flow:<flow id>:ended` or `:failed`, so a failed run never hides among good ones; a line whose run is not known (no `action_id` on its notice: written before, or the run gone; most of dev's were) reads like every other such line and folds with them under `flow:0:ended`; every other kind stands alone.
- `pg_notification_fold()` (`includes/notifications.php`) puts the older runs right after the newest, which carries `fold_count`; each older one carries `fold_head`, is drawn shut (`pg-bell-shut`) and opens with "Show N more" (`bell-row.md`). An opened fold stays open across the 20 s poll (`pgBellOpenFolds`). `tests/notification_bell_fold_test.php`.

## How is a fold drawn in the drawer?
- A flow's runs (`ws_bell_fold_key()`) fold under the newest: its title carries the count `.pg-count` beside it, and `.pg-gather-toggle` "Show N more" / "Hide" opens and closes the runs.
- Each older run stays a `li` of its own (`pg_bell_run()`: read, unread and Delete work as before), drawn as one `.pg-gather-line` lined up under the head's text: the time in bold, what it says, the dot; its quick actions on hover or focus.
- The toggle stays above the open lines (the sheet draws "Hide" under them).

## What does a workspace row keep from the old Workspace inbox?
- Its icon before the title (`--workspace-color`), "Join" for a meeting about to start (a new tab, http(s) only), the address it opens (`ws_bell_describe()`), read in either place read in both (`ws_bell_sync_read()`, `pg_notification_mark_read()`).

## What opens it on Workspace?
- `window.pgBellOpen('workspace')` opens the drawer on Team for this opening (`'ask'` or `'unread'` choose the selector instead) (`pgUI.drawer.show()`, `notifications-menu.md`); the kept choice comes back when it closes. Its callers: the Workspace list's "Notifications" row (`workspace_side.js`) and `workspace.php?view=home&inbox=1`, that row's address; a page without the bell opens the workspace's drawer.

## Where is the Workspace's unread count?
- On the Workspace row of the main menu (`ws_inbox_unread_count()`, `paintRowBadge()`), on the Workspace list's "Notifications" row and on the bell's Team chip: the same rows, one number (`ws_side_payload()['inbox']`).
- The row sits under "Today", the bell's icon, the count as a red pill at its right edge, nothing at zero; a click opens the bell on Team, and the menu never marks it current (`data-pg-menu-skip`). `paintRowBadge()` paints both counts, so on the channel screen they follow `BOOT.inbox` together.
- Owner (2026-11-28, `workflow/decisions-made-panel-20.md`): the menu row showed 29 and the list nothing to match it, so the count had no place in the panel it opens; the row replaces "no Inbox entry" of 2026-11-03.

## Why?
- Owner (2026-10-31, `workflow/decisions-made-workspace-5.md`): since 4.83 every Workspace inbox row was also a bell row, so one item showed in two lists with two counts. `ws_inbox` stays the store for the API, sync and push; the panel is now a 30rem drawer (`notifications-menu.md`).
