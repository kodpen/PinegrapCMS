---
name: kiosk-mode
description: kiosk.php: what its query string sets in the session, how logout restarts it, and why the text parameters must be strings
---

## What does kiosk.php do?
- Without `action` it turns kiosk mode on for the session: `url` (same-origin only, `pg_safe_back_url()`), `inactivity_time`, `dialog_time` (whole positive numbers, else the defaults 30 and 15), `dialog_message`, `continue_button_label`, `logout_button_label` (else the translated defaults), then redirects to `url` or the site root.
- `action=update_activity` marks activity; `action=logout` signs out or clears the session and sends the visitor back to `kiosk.php` with the non-default values in the query string.
- `get_page_content.php` prints the values as JavaScript variables (`software_kiosk_*`) through `escape_javascript()`.

## Why must the text parameters be strings?
- `escape_javascript()` returns a non-scalar unchanged. An array (`?dialog_message[]=x`) was stored in the session and printed "Array" with an "Array to string conversion" warning on every page of the kiosk session. Since #5110 a text parameter is kept only when it is a non-empty string (`tests/kiosk_text_parameters_test.php`).
