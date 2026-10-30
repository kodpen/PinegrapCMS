---
name: time-zone-list-labels
description: How the time zone pick lists label and order their zones (offsets computed from the time zone database, renamed places, values unchanged)
module: core
---

## Where does the '(UTC+03:00)' of a time zone label come from?
- From this PHP's time zone database, not a fixed text: `get_timezones()` replaces each label's leading `(UTC…)` with `pg_timezone_offset_label($zone)` (`includes/fn/core.php`). The offset is the zone's standard one, the smaller of its offsets on 1 January and 1 July of the current UTC year, so summer time on either hemisphere is left out; minutes are kept (+05:30, +05:45, -03:30). Where the zone cannot be built the helper returns '' and the fixed prefix stays.
- The list is ordered by that offset, stably (the original position breaks ties: `usort()` is not stable before PHP 8). It is cached per request when no `$known` callback is given.

## What was wrong before?
- The fixed labels were stale: Moscow showed +04:00 (+03:00 since 2014), Caracas -04:30 (-04:00 since 2016), and Nuuk, Casablanca, Chihuahua, Almaty, Yekaterinburg, Krasnoyarsk, Urumqi, Irkutsk, Yakutsk, Vladivostok and Magadan were off too (owner bug #3458, item 3; PR #3588).
- Three names misled: 'Mid-Atlantic' is now 'Fernando de Noronha' (`America/Noronha`), 'International Date Line West' 'Marshall Islands' (`Pacific/Kwajalein`, +12), 'Samoa' 'American Samoa' (`Pacific/Pago_Pago`). The place names were never `lang()`-wrapped.

## Did any stored value change?
- No. Every zone value is the same string as before: user and site settings store it, and `my_account_profile.php` validates a submitted zone against the values. `tests/core_test.php` pins the set of values.
- A label can differ between PHP builds (Nuuk is -02:00 on current data, -03:00 on PHP 7.1's embedded one), so tests find an entry by its place name, never by its full label.
