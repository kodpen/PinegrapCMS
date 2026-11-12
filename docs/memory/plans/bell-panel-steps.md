---
name: bell-panel-steps
description: Steps of plans/bell-panel.md: BP1 read when opened, BP2 the dashboard's row, BP2b the drawer and the row that fits it, BP3 the filters and the search, BP4 the in-place actions and the close-out
---

## Which steps are there?
- S under 400 product lines, M about 1,000; each step its tests and its memory topic. They are built in the order written: each waits for the one before it.
- Every step edits `assets/js/backend.src.js` and its bundle, BP2, BP2b and BP3 `assets/css/backend.src.css` too: a step starts only while no open PR edits the same file (the claim check).

- BP1 (M) A row is read when it is opened. Done: #8682 (`ui/bell-read-rule.md`, `ui/notifications-menu.md`).
- BP2 (M) The row drawn as the dashboard's Next up row. Done: #8722 (`ui/bell-row.md`). A row's module, day and button, the tag's hues, the dot and the row's menu stay; its layout in the 26rem dropdown was not accepted (#8754).
- BP2b (M) The drawer, and the row that fits it. `free`. Owner 2026-11-11, #8754 (`workflow/decisions-made-panel-4.md`).
  - The panel: `#notifications` becomes a Bootstrap 6 drawer at the right edge through `pgUI` (`ui/pg-ui-adapter.md`), full height, 30rem wide, the whole width on a phone. Head: the name "Notifications", a close button, "Mark all as read", a menu holding "Delete All Notifications"; then the filters, the list (the only part that scrolls), the push line at the foot.
  - Opening: the bell, Ctrl+Q and `pgBellOpen()` open it, Escape and a click outside close it. One list load per open, the 20 s poll's rule and the bell's `show` class as today, on the drawer's events.
  - The row: line one the tag as a small mark, the dot, the title; line two the place and the time, the row's button and menu at the right; then the excerpt, two lines at most, whole in the row's place. No column of the row's height.
  - A short title: a Workspace row says who and what ("<sender> asked you"), its channel or record goes to line two (`ws_bell_describe()`, `ws_inbox_place()`); a site row keeps its title, its details make line two.
  - Tests: the title and place of a Workspace row as a pure function, the drawer's markup, the open and close rules. Topics `ui/bell-row.md`, `ui/notifications-menu.md`.
  - The owner approves the PR's pictures before it merges (`workflow/architect-asking.md`): wide and phone, light and dark, with rows as in his picture (an agent's question, a mention, an Accounting row).
- BP3 (M) The filters and the search.
  - `includes/fn/output.php` prints the chips for everybody: All, the modules, "Asked of me" (each row gets `ask` from its Workspace kind), the "Unread only" switch and the search box. The script shows a module's chip only while the list holds a row of it, counts unread per chip, and applies chip, switch and search together.
  - The kept choice (`pg-bell-filter`): `workspace` reads as Team, `site` as All; `pgBellOpen('workspace')` opens Team. "Delete All Notifications" stays under All only. An empty result says so in one line.
  - Tests: the chips' markup and the kept-choice mapping (source checks). Topic `ui/notifications-one-inbox.md` corrected.
- BP4 (S) The in-place actions and the close-out.
  - A row gets `act` only when the reader may run it: Approve for `approval_requested` (`ws_approval_decide`), Try again for a failed `flow_finished` run (`ws_flow_step_mend`, `ws_flow_share_manager()`), a task done for `assigned`, `task_reminder`, `task_overdue` (`ws_task_status`). After success the row is read and redrawn; a refusal shows the bell's toast (`pg_bell_refused()`).
  - Tests: which rows get `act`, and that a reader without the right gets none.
  - Close-out: the lasting rules stand in the topics above; this plan's two files are deleted.

## Why this order?
- BP1 needs no stylesheet, so it can start while other open PRs hold `backend.src.css`; the dot (BP2) and "Unread only" (BP3) rest on its rule; the chips take the tag's names and hues from BP2 and stand in BP2b's head.
