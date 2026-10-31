---
name: notifications-one-inbox
description: The bell as the panel's one inbox (#3817): All, Site, Workspace filters, a flow's runs folded, the meeting call, pgBellOpen(), the Workspace list's Inbox opening it with no count of its own
---

## What does the bell show for a member of the team?
- `output.php` prints `.pg-bell-filters` (All, Site, Workspace) over the list only when `ws_rights()['member']`; anyone else sees the list as before.
- `pg_panel_get_notifications()` gives each row `group` (`workspace` for `action = 'workspace'`, else `site`), `glyph` (the inbox row's bi- icon), `join` (a meeting's call) and `fold`; `backend.src.js` hides the rows of another filter (`pg-bell-off`) and counts each filter's unread on its chip.
- The choice is the reader's (`pg-bell-filter` in localStorage). Delete All is offered only under All.

## How are a flow's runs folded?
- `ws_bell_fold_key()` (`includes/workspace/notify.php`): `flow:<flow id>:ended` or `:failed`, so a failed run never hides among good ones; every other kind, and a run no longer known, stands alone.
- `pg_notification_fold()` (`includes/notifications.php`) puts the older runs right after the newest, which carries `fold_count`; each older one carries `fold_head`, is drawn shut (`pg-bell-shut`) and opens with "+N more". An opened fold stays open across the 20 s poll (`pgBellOpenFolds`). `tests/notification_bell_fold_test.php`.

## What does a workspace row keep from the old Workspace inbox?
- Its icon before the title (`--workspace-color`), "Join" for a meeting about to start (a new tab, http(s) only), the address it opens (`ws_bell_describe()`), read in either place read in both (`ws_bell_sync_read()`, `pg_notification_mark_read()`).

## How does the Workspace list's Inbox open it?
- `workspace_side.js` `toBell()`: a click calls `window.pgBellOpen('workspace')`, which sets the filter for this opening and clicks the bell; the kept choice comes back when the bell closes. A page without the bell falls back to the screen's own hook or the link (`workspace.php?view=home&inbox=1`, the drawer).
- The Inbox entry shows no count: the bell's is the one. The Workspace row of the main menu keeps its badge (`paintRowBadge()`).

## Why?
- Owner (2026-10-31, `workflow/decisions-made-workspace-5.md`): since 4.83 every Workspace inbox row was also a bell row, so one item showed in two lists with two counts. `ws_inbox` stays the store for the API, sync and push; the panel is 26rem wide for the sentence and the filters.
