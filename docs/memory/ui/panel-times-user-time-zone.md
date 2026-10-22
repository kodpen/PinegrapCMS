---
name: panel-times-user-time-zone
description: Which time zone get_relative_time() and get_absolute_time() draw in (the person's or the site's, pg_time_in_user_zone()), and how stored zone names are read (pg_timezone_canonical(), old IANA links)
---

## Which zone does a time on a panel screen use?
- The signed-in person's own zone (`USER_TIMEZONE`, from their profile) when they have one, otherwise the site's (`TIMEZONE`). A caller asks for the site's with `'timezone_type' => 'site'`; leaving the key out means the person's zone.
- `get_relative_time()` ("3 days ago", the tooltip, and the date of anything older than 30 days) and `get_absolute_time()` (date and time, time alone) both ask `pg_time_uses_user_zone($properties)`, which reads the two constants and hands them to the pure `pg_time_in_user_zone($properties, $signed_in, $user_timezone)` (`includes/fn/core.php`, tested in `tests/core_test.php`). A plain date (`'type' => 'date'`) is always the site's.

## What was wrong before?
- `get_relative_time()` tested `isset($properties['timezone_type']) && … != 'site'`: a missing key read as "site". None of its ~196 callers names the key, so a person's zone never reached a relative time while `get_absolute_time()` used it, and the same record could show one day in a list and another on its own screen (issue #814, fixed in PR #896).

## Why the defined() checks?
- An error screen or a command line job can draw a time before `init.php` has defined `USER_LOGGED_IN` / `USER_TIMEZONE`; the test runner never defines them. Read through `defined()`, nobody signed in means the site's zone instead of an undefined constant error.

## Why can a stored zone name not be passed to DateTimeZone as it is?
- Debian and Ubuntu moved the IANA "backward" links (`US/Eastern`, `Canada/Atlantic`, `Asia/Katmandu`, `America/Godthab` …) to the `tzdata-legacy` package; a PHP that reads the system's database (`php -i`: "0.system") throws `DateInvalidTimeZoneException` for them. A person with such a zone got HTTP 500 on every panel list once relative times followed their zone (issue #966, PR #1266).
- `get_timezones()` offers current names only (16 replaced; `Etc/Greenwich` stays, the one fixed GMT zone). `pg_timezone_legacy_names()` maps old → current; `pg_timezone_canonical($name)` reads a stored name as this PHP knows it (old → current, and current → old where an older database lacks it: `America/Nuuk` is tzdata 2020a, newer than PHP 7.1's), '' when unknown. `USER_TIMEZONE` (auth.php) and `TIMEZONE` (init.php) are defined through it, so an unknown name means the site's or the server's zone; no data step rewrites the stored values.
- The time helpers build zones with `pg_timezone_object()`, which never throws (default zone, then UTC). The pickers select an old stored name as its current option (`pg_timezone_list_value()`). `get_file.php` has no core.php: it only skips a `TIMEZONE` that `date_default_timezone_set()` refuses.
