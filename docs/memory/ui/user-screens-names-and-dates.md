---
name: user-screens-names-and-dates
description: User and session screens: session and password dates through get_absolute_time(), the users list checkbox, yes mark and name-menu names, badge label and folder expiry names
module: user_list
---

## Which zone and format do session and password dates use?
- `get_absolute_time()`: Edit User's sessions (Opened, Expires), `view_sessions.php` (Created, Expires, push Subscribed) and Edit User's password "Last changed" (`'type' => 'date'`). The Sessions cells print the `<time>` element unescaped; Edit User's lines go through `h(lang())`, so they take `'format' => 'plain_text'` (`panel-dates-site-order.md`). They follow the site's 12/24-hour and day/month order and the person's zone, like the "Last used" column beside them (`panel-times-user-time-zone.md`).
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

## Where are the Sessions screen's and users list's reasons?
- Printed as small `fg-secondary` text, never only in a `title` (a hover-only popover, and Bootstrap 6's `_fixTitle()` drops it for screen readers on an element with text): the push "N failed attempts" badge's `last_error` (`text-break`, the failed-test notice points there), the bare dash and "Signed out" of a push row, the session "Locked" badge, the users list "Locked" badge's "Clears in N minutes." (server-paged rows get no ready-time hints) and the "{provider} only" warning of `pg_signin_identity_badges()`.
- With "Refuse new sign-ins when the limit is full" and a limit of one, the Locked button of an unpinned session is `aria-disabled="true"` (no `disabled`, which takes no focus and, through `pointer-events:none`, no hover) and `aria-describedby="pg_pin_redundant_reason"`, a `form-text` printed once above the sessions table.
- Test, Stop and Lock/Unlock keep their detail as a compact tooltip (`no-popover` + `data-bs-toggle="tooltip"`), shown on hover and focus. Test: `tests/session_screen_reasons_test.php` (#3182).
