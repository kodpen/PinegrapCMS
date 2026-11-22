---
name: bell-filters
description: The bell's filters (BR1): the selector All / Unread / Asked of me, module chips as toggles on one line, the search and its marks, the kept choice and the old one read once, Delete All's rule
---

## Which filters does the bell have?
- For everybody (`output.php`, `.pg-bell-filters`; BR1 #11110, owner 2026-11-22 in `workflow/decisions-made-panel-16.md`): one segmented control `.pg-bell-seg` over the whole width, All, Unread (count), Asked of me (count).
- Under it the module chips `.pg-bell-chips` on one line (Shop, Accounting, Content, Team, System: the row's `module`, `bell-row.md`), each with its tone's dot and unread count. The search is in the head (`notifications-menu.md`). Site and Workspace chips are gone (BP3).
- A chip is a toggle: pressed it shows its module, pressed again every module; there is no All chip. A chip shows only while a row of it is in the list (the pressed one always), the line only with two modules or more (or a chip pressed); it scrolls sideways.
- "Asked of me": a row with `ask` = 1, `pg_notification_asks()` (`includes/notifications.php`): a Workspace `mention`, `question_asked` or `approval_requested`.
- The list is loaded whole; `pg_bell_filter_apply()` (`backend.src.js`) applies the selector, the chip and the search together (`pg_bell_row_matches(row, filter, view, search)`), counts the unread rows for the selector, the chips and the "N new" pill.
- The search reads the title, the place and the excerpt, lower case, the Turkish dotted and dotless i as one letter; `pg_bell_search_mark()` marks the matches in the shown titles and texts (`mark.pg-bell-mark`, inserted as text).
- Unread keeps a row read since the list was drawn in sight (`data-pg-bell-was-new`), so a row pressed read does not vanish under the pointer; choosing Unread starts from the rows unread now.
- What the list shows when the filters leave nothing (no match with "Clear filters", Unread with nothing unread): `bell-states-and-foot.md`. The filters stand only over rows or while they load: not over the empty or the failed state.

## What does the bell keep?
- The selector in `pg-bell-view`, the chip in `pg-bell-filter` (localStorage); the search is cleared when the drawer closes. A kept `workspace` reads as Team, a kept `site` as All (`pg_bell_filter_name()`).
- The choices kept before the selector are read once (`pg_bell_kept_upgrade()`, while `pg-bell-view` is unset): a kept `ask` chip becomes Asked of me (it wins over the switch) and `pg-bell-filter` goes back to `all`; `pg-bell-unread` = 1 becomes Unread; `pg-bell-unread` is removed; a kept module chip stays.
- "Delete All Notifications" acts only under All with no chip and no search, and with two rows or more; otherwise it stands disabled in the head's menu (`bell-head.md`).
- Each row keeps `group` (`workspace` or `site`) in the endpoint's answer and as `data-pg-bell-group`; no filter reads it now.

Tests: `tests/bell_filters_test.php`, `tests/bell_drawer_test.php`. Related: `notifications-one-inbox.md`, `notifications-menu.md`.
