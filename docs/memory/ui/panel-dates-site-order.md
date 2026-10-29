---
name: panel-dates-site-order
description: Application Access, Connected applications, Connections, the chat window and the barcode scan logs show dates in the site's date order and 12/24-hour clock
---

## Which screens print their dates through get_absolute_time()?
- `api_settings.php`: a device's "since", an application's created date and an OAuth client's "Registered on" (`'type' => 'date'`).
- `connected_apps.php` and `connections.php`: "Connected on" (`'type' => 'date'`).
- `chat.php`: `pg_chat_last_seen_label()` past a week (`'type' => 'date'`) and the "Last seen" tooltip `pg_chat_last_seen_exact()` (date and time).
- Before, they were `date('d.m.Y')` / `date('d.m.Y H:i')`: a US-English site (`month_day`, `twelve_hours`) read "29.10.2026" beside a "Last used" that followed its settings.
- Left as they are: `api_settings.php`'s log `'time'` (H:i) and `'expires'` (Y-m-d, an input value).

## How does the chat window know the site's clock?
- `chat.php` puts `'clock' => array('date_order' => 'month_day'|'day_month', 'twelve_hours' => bool)` in the window's config. `formatTime()` in `chat_backend.src.js` draws "2:05 PM" or "14:05" today, and "10/29 2:05 PM" or "29.10 14:05" on another day. The `.min.js` twin is rebuilt by hand (`chat-window-keyboard.md`).

## Where does the barcode scan log time come from?
- `update_inventory_quantity` in `barcode_decrease_inventory.php` and `barcode_increase_inventory.php` returns `data.scanned_at`, `get_absolute_time(array('timestamp' => time()))`, and the log row prints it. The page built it by hand as "29/10/2026 9:5:3" (unpadded, neither the site's order nor its clock). The endpoint's success answer is unchanged otherwise.

Test: `tests/panel_dates_site_order_test.php`.
