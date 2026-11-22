---
name: bell-states-and-foot
description: The bell drawer's list states (BR3 #11154): skeleton rows, failed, empty, all read, no match, each with its button; the foot as a switch row and its refused line
---

## Which states can the list show?
- One at most, chosen by `pg_bell_state(state, live, shown, view, unread)` (`backend.src.js`) inside `pg_bell_filter_apply()`; `pgBellState` is `loading`, `failed` or `ready`.
- Loading: `pg_bell_skeleton()` puts a heading's bar and four rows (a 28 px tile, two bars) in the list, "Loading..." for a screen reader; it shines only without reduced motion (`ws-skeleton-shine`). No spinner.
- Failed: a rose tile, "Notifications could not be loaded", the server's sentence, "Try again" (`bell-refusals.md`).
- Empty (no row at all, also after the last is deleted): a gray bell tile, "No notifications", "New orders, forms and team messages appear here."
- All read (Unread chosen, nothing unread): a teal tile, "Everything is read" (the key "All read" is the "Hepsini oku" button elsewhere), "No unread notifications.", "Show all" chooses All.
- No match (filters or search leave no row): a gray funnel tile, "No notification matches these filters.", "Clear filters": All, no chip, the search emptied; both choices kept as the filters' own.
- The filters stand over rows, loading, all read and no match; not over empty or failed.

## How are they drawn?
- Printed hidden beside the list by `pg_bell_state_note($key, $tone, $icon, $title, $text, $button)` (`includes/notifications.php`, pure; `output.php` loads that file): the Design examples' empty screen (`.ws-empty-screen`, `.pg-icon-tile`, `.pg-title-section`, `.pg-text-2`, a quiet small button `data-pg-bell-note-act`), `.pg-bell-note` filling the room under the filters; `role="status"`, the failed one `role="alert"`.
- Before BR3: a spinner, a red line, a "No Notification" text with a wind icon, a one-line no-match sentence.

## What is the foot?
- `#push_row`: a switch row, the bell icon (filled while this device is told), the label "Notifications on this device" and `input#push_toggle.pg-switch.pg-push-toggle` (`role="switch"`). `pg_push_paint()` sets `checked`; a click that starts no request (the browser refusing, nothing ready) puts the switch back; a running one disables it (`pg_push_busy()`).
- `#push_hint` where the push cannot be offered: a muted amber bell-slash. When the browser refuses, "Notifications are off for this site" over "Turn them on in the browser, or in the notification settings of the installed application." (`pg_push.strings.blocked_hint`); on iOS before the panel is on the home screen, only the home-screen sentence.
- The system status card keeps its own Turn on / Turn off button (`.pg-push-toggle` with `.pg-push-label`).
- Before BR3 the foot was a "Turn on" / "Turn off" text button.

## How was it checked?
- On the sandbox at 1440 and 390 px, light and dark, every state above, with site rows and Workspace mention and question rows unread and read made with `ws_notify()`. Headless Chromium grants no notification permission: the switch row was painted with `pg_push_paint()` in the page.

Tests: `tests/bell_drawer_test.php` (`test_bell_drawer_state_choice`), `tests/bell_row_test.php` (`test_bell_state_note`), `tests/bell_and_edoc_refusal_test.php`. Drawing: `bell-redraw-drawing/bell.html` (Z3).
