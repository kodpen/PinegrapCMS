---
name: bell-head
description: The bell drawer's head (BR1, BR3): N new pill, Search, More actions and Close at the right edge, the menu always standing with disabled items, the search in the head's place
---

## What is in the head?
- Head `.pg-bell-head`, 56 px (BR1 #11110): `.pg-bell-head-main` holds the heading "Notifications", the pill `.pg-bell-new` "N new" (while a row is unread), then three `pg-icon-button`s: Search `.pg-bell-search-open`, More actions (`.pg-bell-head-menu`), Close `.pg-bell-close`. No text button in the head.
- The three buttons stay at the right edge whether the pill shows or not: the auto margin is on Search (`.pg-bell-search-open`), not on the pill. Before BR3 (#11154) the pill held it, so with nothing unread Search and Close stood against the title.
- More actions always stands (BR3): its menu holds "Mark all as read" above "Delete All Notifications", both always printed; one with nothing to act on now is `disabled` (grey, skipped by the menu's keys, `pg_bell_menu_items()`).
- Disabled: Mark all with nothing unread; Delete All unless All, no chip, no search, two rows or more and the list loaded. Opening the drawer disables both till the counts arrive.
- Before, the menu was hidden (`d-none`) with neither, so the head had two buttons in one state and three in the next.
- An item the person may never use is not printed; every reader may use both (each acts only on the rows the reader sees). "Mark all as read" also stands at the right of the list's first shown day heading (`.pg-bell-day-all`), not while a search is open.
- Search swaps `.pg-bell-head-main` for `.pg-bell-search-bar` (back arrow, the field `#pg_bell_search`, clear); the back arrow or Escape closes it, clears it and focuses Search (`pg_bell_search_show()`).
- Every head button is `pg-icon-button`; without it the browser drew a bordered grey button (#11086).

## In which order does Escape close things?
- An open menu first, then an open search, then the drawer (`notifications-menu.md`).

Tests: `tests/bell_drawer_test.php`, `tests/bell_read_rule_test.php`. Drawing: `bell-redraw-drawing/bell.html`.
