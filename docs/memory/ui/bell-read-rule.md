---
name: bell-read-rule
description: When a bell row is read: not on opening the bell; its link or Mark as read box reads it, a fold head its runs; Mark all as read reads only rows the caller sees
---

## When is a bell row read?
- Owner (2026-11-11, `workflow/decisions-made-panel-4.md`): when it is opened or its box is ticked, never by opening the bell; "Mark all as read" stands at the top. The bell's count stays until then.
- `pg_panel_get_notifications()` (`includes/panel/notifications.php`) marks nothing and takes no `read_mark`; its `pg_panel_actions()` row says `'write' => false`.
- Before, every list request (`get_notifications(true)` on each open) marked every row it drew read, and the badge was cleared before the request.

## Which requests read a row?
- `edit_notifications` with `do_action`:
  - `mark_read`: the row `id` if `pg_notification_visible_to()` lets the caller see it, plus the ids in `runs` the caller sees (`pg_notification_run_ids()`: whole positive ids, once, not the head, at most 500);
  - `mark_all_read`: every notification the caller sees (`id` is ignored);
  - `mark_unread`, `remove`: one row, as before.
- Both reading jobs go through `pg_notification_readable_ids($rows, $sees)` (pure, `includes/notifications.php`) and then `pg_notification_mark_read()`, so a Workspace row read here is read in the Workspace too. Read in the Workspace stays read in the bell (`ws_bell_sync_read()`), unchanged.

## Who collects a fold's runs?
- The script: a fold's head sends the ids of the rows drawn under it (`li[data-pg-bell-fold="<head>"]`) as `runs`; the server checks each one. Opening an older run reads only that run.

## What does the script do?
- `get_notifications(keep_place)` (`backend.src.js`): no `read_mark`; the badge is left as it is.
- Above the list: "Mark all as read" (`.pg-bell-mark-all`, hidden while nothing is unread) beside "Delete All Notifications" (shown under All only, more than one row). Mark all turns the rows' classes in place, no redraw.
- Every row, custom ones too, has the box `pg_bell_read_button()`: "Mark as read" on an unread row, "Mark as unread" on a read one; it turns the row's `new`/`viewed` classes and itself (`pg_bell_row_state()`). BP2 replaces it with the dot.
- "Go to the relevant page" and "Join" go through `pg_bell_open_row()`: an unread row sends `mark_read` and the page opens when the answer comes, success or not (`complete`); Join opens its tab at once (a popup must follow the click).
- A refused read puts the row back and shows the bell's toast (`ui/bell-refusals.md`); after every answer `check_unread_notifications('editcallback_check')` sets the badge.

Pinned in `tests/bell_read_rule_test.php`; the poll rule in `ui/notifications-menu.md`.
