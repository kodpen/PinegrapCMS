---
name: bell-redraw-steps
description: Plan bell-redraw, steps BR1 to BR3 with size, order and checks: the head, selector, chips and search; the rows and short times; the states, the foot and the close-out
---

## Which steps?
- BR1 (M) first, done in #11110: the head and the filters. Head with the "N new" pill and the three icon buttons; the segmented control All / Unread / Asked of me in place of the "Unread only" switch and the "Asked of me" chip; the chips on one line as toggles, no All chip; the search field opening in the head; "Mark all as read" at the first day heading and in the head's menu.
- In BR1 the kept choices are read once: `pg-bell-unread` on becomes Unread, a kept `ask` chip becomes Asked of me, a kept module chip stays; the search is still cleared on close. "Delete All Notifications" keeps its rule (All, no module, no search, two rows or more).
- BR2 (M) after BR1, done in #11134: the rows. The unread tint and the read row's dim title and tile; the short time from one pure function with its test (minutes, hours, Yesterday, day and month; the full date and time stays as the title attribute); the phone's order time, dot, menu with a 32 px menu button; day headings that stay at the top of the list.
- BR3 (M) after BR2, in progress: the states (skeleton rows, failed with "Try again", empty, "All read", no match with "Clear filters"), the foot as a switch row and its refused line, then the close-out.
- Close-out in BR3: `ui/notifications-menu.md`, `ui/notifications-one-inbox.md`, `ui/bell-row.md` and `ui/bell-refusals.md` corrected to what was built; the drawing stays in `ui/bell-redraw-drawing/`; this plan's two files deleted.

## Which steps share a file?
- All three enter the bell's block of `includes/fn/output.php`, `assets/js/backend.src.js` and `assets/css/backend.src.css`: one worker at a time, each after the one before has landed. Before BR1, check the open PRs for the bell (`…/pulls/<n>/files`).

## What does every step check?
- At 390 px and 1440 px, dark and light, on a sandbox with rows of each module, a fold, a row with a button and a read row: the PR says so with what it saw. A unit test cannot reach the look, so the sandbox run is this plan's reason.
- The keys of `ui/bell-row.md` still work: Tab order, the menu's arrows, Escape closing the menu, then the search, then the drawer; the focus returns as before.
- Every new sentence through `lang()` and the panel's run-time dictionary, the Turkish with `php tools/tr_add.php`; `tests/bell_drawer_test.php`, `bell_row_test.php`, `bell_read_rule_test.php`, `bell_and_edoc_refusal_test.php` kept true, changed where the markup changes; `php tools/check_look.php`, `check_bs6.php --strict`. The bundles are built to try and not committed.
