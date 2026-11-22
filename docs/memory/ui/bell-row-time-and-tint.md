---
name: bell-row-time-and-tint
description: A bell row's short time (pg_notification_short_time(), BR2 #11134), the full time as its title, the unread tint, the phone's head order with the 32 px menu button, sticky day headings
---

## What time does a bell row show?
- `pg_notification_short_time($timestamp, $now, $zone, $date_format)` (`includes/notifications.php`, pure): today "5 min" or "3 h" (whole minutes and hours, at least "1 min", a time after now too); then "Yesterday"; then "9 Nov", or "Nov 9" where `DATE_FORMAT` is month_day; the year only when it is not this year.
- It reads the same zone as `pg_notification_day()`, so the time and the heading over it never disagree: 23 hours back after midnight is "Yesterday", not "23 h".
- `get_notifications` sends `time` (short), `time_title` (full date and time, `get_absolute_time()`) and `datetime` (UTC). `pg_bell_time()` draws them escaped as one `<time>` with the full one as its title, in a row's head and a fold's run line.
- Keys `{var:1} min` (dk), `{var:1} h` (sa), `Yesterday` were already translated.

## How is an unread row told apart?
- `li.new > .pg-bell-card` carries 6.5 % of the accent (`color-mix`), 11 % on hover or focus. A read row has no tint; the recipe's `.is-read` dims its title and tile. `pg_bell_row_state()` switches `new`, so the tint follows Mark as read and Mark as unread.

## How does a row's head read on a phone?
- With `(hover: none)`: title, time, the dot `.pg-bell-dot-head` (unread only), then the menu button at 32 px; the side column with the desktop dot is hidden. "Mark as read" is in the menu.
- On the desktop the head dot is hidden; hover or focus puts the quick actions in the time's place.

## Do the day headings scroll away?
- No: `li.pg-bell-day` is `position: sticky; top: 0` on `--pg-bg-body`, z-index 2; the next heading covers it. An open row menu (z-index 5) still covers a heading.

Tests: `tests/bell_row_test.php` (`test_bell_row_short_time`, `test_bell_row_short_time_tint_and_sticky_days`).
