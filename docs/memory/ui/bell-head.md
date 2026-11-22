---
name: bell-head
description: The bell drawer's head (BR1): the N new pill, Search, More actions and Close as icon buttons, the menu's two items, Mark all as read at the first day heading, the search in the head's place
---

## What is in the head?
- Head `.pg-bell-head`, 56 px (BR1, plan bell-redraw): `.pg-bell-head-main` holds the heading "Notifications", the pill `.pg-bell-new` "N new" (while a row is unread), then three `pg-icon-button`s: Search `.pg-bell-search-open`, More actions (`.pg-bell-head-menu`), Close `.pg-bell-close`. No text button in the head.
- The head's menu holds "Mark all as read" (while a row is unread) above "Delete All Notifications" (All, no chip, no search, two rows or more); with neither it is hidden. "Mark all as read" also stands at the right of the list's first shown day heading (`.pg-bell-day-all`), not while a search is open.
- Search swaps `.pg-bell-head-main` for `.pg-bell-search-bar` (back arrow, the field `#pg_bell_search`, clear); the back arrow or Escape closes it, clears it and focuses Search (`pg_bell_search_show()`).
- Every head button is `pg-icon-button`; without it the browser drew a bordered grey button (#11086).

## In which order does Escape close things?
- An open menu first, then an open search, then the drawer (`notifications-menu.md`).

Tests: `tests/bell_drawer_test.php`, `tests/bell_read_rule_test.php`. Drawing: `bell-redraw-drawing/bell.html`.
