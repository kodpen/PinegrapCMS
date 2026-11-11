---
name: bell-panel-steps
description: Steps of plans/bell-panel.md: BP1 a row is read when opened, BP2 the row drawn as the dashboard's Next up row, BP3 the filters and the search, BP4 the in-place actions and the close-out
---

## Which steps are there?
- S under 400 product lines, M about 1,000; each step its tests and its memory topic. They are built in the order written: each waits for the one before it.
- Every step edits `assets/js/backend.src.js` and its bundle, BP2 and BP3 `assets/css/backend.src.css` too: a step starts only while no open PR edits the same file (the claim check).

- BP1 (M) A row is read when it is opened. No stylesheet change. Done: #8682.
  - `pg_panel_get_notifications()` (`includes/panel/notifications.php`) marks nothing on a list request. `pg_panel_edit_notifications()` learns `mark_read` (one row; a fold's head reads its runs too) and `mark_all_read` (only the rows the caller sees, `pg_notification_visible_to()`), both through `pg_notification_mark_read()`.
  - The script (`get_notifications()`, `edit_notification()`): the row's link marks it read and then opens; "Mark all as read" stands at the top beside "Delete All Notifications"; the row's box turns both ways (read, unread) until BP2 draws the dot.
  - The 20 s poll: `$unreadsCallback` holds the count the open list shows instead of dropping to 0, so the open list is still redrawn only on a rise (`tests/panel_timers_test.php`).
  - Tests: the two new actions and whose rows they touch. Topics: `ui/notifications-menu.md` corrected, `ui/bell-read-rule.md` new.
- BP2 (M) The row drawn as the dashboard's Next up row.
  - Each row gets `module` (a pure function of `action`), `day` (`today`, `yesterday`, `earlier`, in the site's time zone, pure with the time passed in) and `button` (reply, show, join, approve; from `action` and the Workspace kind of `ws_bell_describe()`).
  - The script draws the tag, the title, the second line, the one button, the dot (a named button: "Mark as read") and the row's menu (Delete, "Mark as unread"); day headings, a fold's runs under their head's day. The two full-width lines go.
  - `--pg-bell-tag-*` in `backend.src.css`, light and dark; nothing scrolls sideways at phone width; the keyboard reaches the button, the dot and the menu.
  - Tests: the module of every known `action`, the day, the tag hues equal to `.pg-dash-queue-tag[data-module]` of `pg_dash.src.css`; `tests/notification_bell_fold_test.php` stays green. Topic `ui/bell-row.md`; `workspace/workspace-menu-tools.md` loses its "compact rows in the bell" line.
- BP3 (M) The filters and the search.
  - `includes/fn/output.php` prints the chips for everybody: All, the modules, "Asked of me" (each row gets `ask` from its Workspace kind), the "Unread only" switch and the search box. The script shows a module's chip only while the list holds a row of it, counts unread per chip, and applies chip, switch and search together.
  - The kept choice (`pg-bell-filter`): `workspace` reads as Team, `site` as All; `pgBellOpen('workspace')` opens Team. "Delete All Notifications" stays under All only. An empty result says so in one line.
  - Tests: the chips' markup and the kept-choice mapping (source checks). Topic `ui/notifications-one-inbox.md` corrected.
- BP4 (S) The in-place actions and the close-out.
  - A row gets `act` only when the reader may run it: Approve for `approval_requested` (`ws_approval_decide`), Try again for a failed `flow_finished` run (`ws_flow_step_mend`, `ws_flow_share_manager()`), a task done for `assigned`, `task_reminder`, `task_overdue` (`ws_task_status`). After success the row is read and redrawn; a refusal shows the bell's toast (`pg_bell_refused()`).
  - Tests: which rows get `act`, and that a reader without the right gets none.
  - Close-out: the lasting rules stand in the topics above; this plan's two files are deleted.

## Why this order?
- BP1 needs no stylesheet, so it can start while other open PRs hold `backend.src.css`; the dot (BP2) and "Unread only" (BP3) rest on its rule; the chips take the tag's names and hues from BP2.
