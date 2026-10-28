---
name: user-screens-names-and-dates
description: User and session screens: session and password dates through get_absolute_time(), the users list checkbox, yes mark and name-menu names, badge label and folder expiry names
module: user_list
---

## Which zone and format do session and password dates use?
- `get_absolute_time()`: Edit User's sessions (Opened, Expires), `view_sessions.php` (Created, Expires, push Subscribed) and Edit User's password "Last changed" (`'type' => 'date'`). They follow the site's 12/24-hour and day/month order and the person's zone, like the "Last used" column beside them (`panel-times-user-time-zone.md`).
- Before, they were `date('Y-m-d H:i')` / `date('d.m.Y')`: the site's zone and one fixed format, so one row mixed two zones.

## How are the users list controls named?
- Row checkbox: `pg_user_list_select_cell()`, aria-label "Select {var:1}" with the username (the disabled last-administrator box too).
- Yes cells: `pg_user_list_mark()`, the `task_alt` ligature `aria-hidden` and a `visually-hidden` "Yes".
- Folder, calendar, form and contact group cells: `pg_user_list_names_cell($names, $icon, $label)`; the button carries `$label` (Folders, Calendars, Forms, Contact Groups), the ligature is hidden, and an empty list draws a dash instead of a button with an empty menu.

## What else is named?
- `#badge_label` on add, edit and import user has aria-label Badge Label: its placeholder is the site's BADGE_LABEL default when one is set.
- The Private Content folder expiry box (`includes/fn/auth.php`) has aria-label "Expiration date of {var:1}" with the folder name; its clear button "Remove the expiry".
- The toolbar and save bar landmarks are `h(lang('Button Bar'))` and `h(lang('data edit buttons'))`.

Test: `tests/user_screens_names_test.php`.
