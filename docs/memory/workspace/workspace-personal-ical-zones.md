---
name: workspace-personal-ical-zones
description: How a TZID of a person's own iCal calendar or a mail invitation is read: Outlook's Windows names, (UTC+hh:mm) labels, IANA names, the site-zone fallback (#9707)
---

## How is a TZID turned into a zone?
- `ws_personal_ical_moment()` calls `ws_ical_zone($tzid)` (`includes/workspace/ical_zone.php`): a Windows name ("GMT Standard Time", any case) through `ws_ical_windows_zones()` (CLDR windowsZones, territory 001); a display label `(UTC+03:00) Istanbul` at its fixed offset, `(UTC) …` as UTC; anything else as an IANA name. A name that matches none gives null and the time is read in the site's zone.
- Both the own iCal calendar (`ws_personal_ical_events()`) and mail invitations (`ws_mail_invitation_read()`: the card, "Update my plan", the saved plan) read through it.

## Why?
- Outlook and Exchange write Windows zone names that PHP does not know; they were read in the site's zone, so a 10:00 London meeting showed at 10:00 on a Europe/Istanbul site instead of 13:00 (#9707, issue #9664).

## Why does an entry hold two names?
- A zone renamed by tz (Kyiv, Nuuk, Yangon) is written `new|old`; the first one PHP knows wins. An older database knows only the old name; a system database without the backward links (Ubuntu's PHP 8.3) knows only the new one.

## What is still not read?
- The VTIMEZONE block of the file: a custom zone name found neither in the table nor in PHP stays in the site's zone. A display label gives only its offset, not the clock-change rule.
- Test: `test_workspace_personal_ical_windows_zone` (every table entry must give a zone the running PHP knows) and the Outlook case in `tests/workspace_personal_plan_mail_test.php`.
