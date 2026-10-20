---
name: panel-times-user-time-zone
description: Which time zone get_relative_time() and get_absolute_time() draw in: the signed-in person's own zone unless the caller asks for the site's, decided by pg_time_in_user_zone()
---

## Which zone does a time on a panel screen use?
- The signed-in person's own zone (`USER_TIMEZONE`, from their profile) when they have one, otherwise the site's (`TIMEZONE`). A caller asks for the site's with `'timezone_type' => 'site'`; leaving the key out means the person's zone.
- `get_relative_time()` ("3 days ago", the tooltip, and the date of anything older than 30 days) and `get_absolute_time()` (date and time, time alone) both ask `pg_time_uses_user_zone($properties)`, which reads the two constants and hands them to the pure `pg_time_in_user_zone($properties, $signed_in, $user_timezone)` (`includes/fn/core.php`, tested in `tests/core_test.php`). A plain date (`'type' => 'date'`) is always the site's.

## What was wrong before?
- `get_relative_time()` tested `isset($properties['timezone_type']) && … != 'site'`: a missing key read as "site". None of its ~196 callers names the key, so a person's zone never reached a relative time while `get_absolute_time()` used it, and the same record could show one day in a list and another on its own screen (#814).

## Why the defined() checks?
- An error screen or a command line job can draw a time before `init.php` has defined `USER_LOGGED_IN` / `USER_TIMEZONE`; the test runner never defines them. Read through `defined()`, nobody signed in means the site's zone instead of an undefined constant error.
