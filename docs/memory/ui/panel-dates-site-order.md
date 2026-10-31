---
name: panel-dates-site-order
description: Application Access, Connected applications, Connections, the chat window and the barcode scan logs show dates in the site's date order and 12/24-hour clock; chat times in the panel's zone
---

## Which screens print their dates through get_absolute_time()?
- `api_settings.php`: a device's "since", an application's created date and an OAuth client's "Registered on" (`'type' => 'date'`).
- `connected_apps.php` and `connections.php`: "Connected on" (`'type' => 'date'`).
- `chat.php`: `pg_chat_last_seen_label()` past a week (`'type' => 'date'`) and the "Last seen" tooltip `pg_chat_last_seen_exact()` (date and time).
- Before, they were `date('d.m.Y')` / `date('d.m.Y H:i')`: a US-English site (`month_day`, `twelve_hours`) read "29.10.2026" beside a "Last used" that followed its settings.
- Subscriptions, Firewall log, short link state lines and the Paraşüt line: `stored-times-site-order.md`.
- Left as they are: `api_settings.php`'s log `'time'` (H:i) and `'expires'` (Y-m-d, an input value).

## How does the chat window know the site's clock?
- `chat.php` puts `'clock' => array('date_order', 'twelve_hours', 'zone')` in the window's config. `formatTime()` in `chat_backend.src.js` draws "2:05 PM" or "14:05" today, and "10/29 2:05 PM" or "29.10 14:05" on another day. The `.min.js` twin is rebuilt by hand (`chat-window-keyboard.md`).

## Which zone does the chat window draw its times in?
- The panel's rule (`panel-times-user-time-zone.md`): `clock.zone` is `pg_timezone_canonical(USER_TIMEZONE)` when `pg_time_uses_user_zone(array())`, else `date_default_timezone_get()` (the site's). `zoneParts()` reads the message's and "now"'s date and time in it through `Intl.DateTimeFormat(…).formatToParts`, so "same day" is that zone's midnight; an unknown name falls back to the browser's getters.
- Symptom: another browser zone read "11:05" in the list beside "Last seen" "14:05 +03".

## Where does the barcode scan log time come from?
- `update_inventory_quantity` in `barcode_decrease_inventory.php` and `barcode_increase_inventory.php` returns `data.scanned_at`, `get_absolute_time(array('timestamp' => time()))`, and the log row prints it. The page built it by hand as "29/10/2026 9:5:3" (unpadded, neither the site's order nor its clock).

## Why do some dates take format plain_text?
- `get_absolute_time()` returns `<time datetime="…">…</time>` unless it gets `'format' => 'plain_text'`. A date that stands alone in HTML prints the element unescaped; a date that goes into a `lang()` var, an attribute, a string later passed through `h()`/`esc()`, JSON drawn as `textContent`/`title`, or CSV takes `plain_text` and is still escaped where printed.
- Symptom: screens showed `<time datetime="…">Pzt 19 Eki 2026</time>` as text (#3605): Sessions, Edit User, the location database note (Languages and WAF Country Restriction cards), Connected applications, Connections, chat "Last seen", the storefront Order Preview and Shipping Method arrival dates, the printed order's arrival date, the visitor report CSV (#3675). The screens moved to `get_absolute_time()` kept their `h()`.
- View Order's arrival date and delivered `title`, and the API settings device "since", application created date and OAuth client "Registered on" take `plain_text` too (#3696). `tests/absolute_time_text_test.php` checks every escaped call in the tree, with no skipped file.

## Where does a relative time's tooltip come from?
- `get_relative_time()` draws its `<time title="…">` with `get_absolute_time(… 'format' => 'plain_text')` for the same moment (date only for `'type' => 'date'`), escaped with `h()`. The caller's properties pass through, so the zone rule (`pg_time_uses_user_zone()`) is the same.
- Symptom: a Turkish panel's tooltip read "Thu 15 Oct 2026 22:11 +03" beside "Per 15 Eki 2026 22:11 +03" elsewhere (#3606, #3712). Test: `test_core_relative_time_tooltip_follows_the_panel_language` in `tests/core_test.php` (Turkish child process).

Tests: `tests/panel_dates_site_order_test.php`, `tests/absolute_time_text_test.php`.
