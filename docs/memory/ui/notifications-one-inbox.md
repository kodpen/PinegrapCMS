---
name: notifications-one-inbox
description: The bell as the one inbox (#3817): module chips, Asked of me, Unread only, the search (BP3), the kept choice, a flow's runs folded, pgBellOpen() for the old inbox address, the Workspace count
---

## Which filters does the bell have?
- For everybody (`output.php`, `.pg-bell-filters`): All, a chip per module (Shop, Accounting, Content, Team, System: the row's `module`, `bell-row.md`) and "Asked of me"; then `.pg-bell-tools`, the search box `#pg_bell_search` and the "Unread only" switch `#pg_bell_unread_only`. Site and Workspace chips are gone (BP3, owner 2026-11-11, `workflow/decisions-made-panel-4.md`).
- "Asked of me": a row with `ask` = 1, `pg_notification_asks()` (`includes/notifications.php`): a Workspace `mention`, `question_asked` or `approval_requested`.
- The list is loaded whole; `pg_bell_filter_apply()` (`backend.src.js`) applies chip, switch and search together (`pg_bell_row_matches()`), shows a chip only while a row of it is in the list (the chosen one always), and counts each chip's unread rows.
- The search reads the title, the place and the excerpt, lower case, the Turkish dotted and dotless i as one letter.
- "Unread only" keeps a row read since the list was drawn in sight (`data-pg-bell-was-new`), so a row pressed read does not vanish under the pointer; turning the switch on starts from the rows unread now.
- An empty result says "No notification matches these filters." (`.pg-bell-no-match`); "No Notification" stays for a list with no row.

## What does the bell keep?
- The chip in `pg-bell-filter`, the switch in `pg-bell-unread` (localStorage); the search is cleared when the drawer closes. A kept `workspace` reads as Team, a kept `site` as All (`pg_bell_filter_name()`).
- "Delete All Notifications" is offered only under All with no switch and no search, and with two rows or more.
- Each row keeps `group` (`workspace` or `site`) in the endpoint's answer and as `data-pg-bell-group`; no filter reads it now.

## How are a flow's runs folded?
- `ws_bell_fold_key()` (`includes/workspace/notify.php`): `flow:<flow id>:ended` or `:failed`, so a failed run never hides among good ones; a line whose run is not known (no `action_id` on its notice: written before, or the run gone; most of dev's were) reads like every other such line and folds with them under `flow:0:ended`; every other kind stands alone.
- `pg_notification_fold()` (`includes/notifications.php`) puts the older runs right after the newest, which carries `fold_count`; each older one carries `fold_head`, is drawn shut (`pg-bell-shut`) and opens with "+N more". An opened fold stays open across the 20 s poll (`pgBellOpenFolds`). `tests/notification_bell_fold_test.php`.

## What does a workspace row keep from the old Workspace inbox?
- Its icon before the title (`--workspace-color`), "Join" for a meeting about to start (a new tab, http(s) only), the address it opens (`ws_bell_describe()`), read in either place read in both (`ws_bell_sync_read()`, `pg_notification_mark_read()`).

## What opens it on Workspace?
- `window.pgBellOpen('workspace')` opens the drawer on Team for this opening (`pgUI.drawer.show()`, `notifications-menu.md`); the kept choice comes back when it closes. Its caller since #6281 is `workspace.php?view=home&inbox=1`, the list's old Inbox address; a page without the bell opens the workspace's drawer.

## Where is the Workspace's unread count?
- On the Workspace row of the main menu (`ws_inbox_unread_count()`, `paintRowBadge()`) and on the bell's Team chip: the same rows. The list has had no Inbox entry since #6281 (owner 2026-11-03, `workflow/decisions-made-workspace-6.md`: the bell already shows them).

## Why?
- Owner (2026-10-31, `workflow/decisions-made-workspace-5.md`): since 4.83 every Workspace inbox row was also a bell row, so one item showed in two lists with two counts. `ws_inbox` stays the store for the API, sync and push; the panel is now a 30rem drawer (`notifications-menu.md`).
