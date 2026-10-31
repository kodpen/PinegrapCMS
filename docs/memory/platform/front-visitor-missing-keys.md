---
name: front-visitor-missing-keys
description: Front-end paths a visitor without a session or page properties reaches must not read session or property keys unguarded: the warning prints before the DOCTYPE where display_errors is on
---

## Why does an unguarded key matter on a front-end path?
- An E_WARNING goes to `data/temp/php_errors.log` on every request, and where `display_errors` is on it prints the server path before `<!DOCTYPE html>`: the page drops to quirks mode and the long path widens it past a phone's width. Guard with `isset()` / `??` (PHP 7.1: `??` is fine, `??=` is not).

## Which reads were guarded (PR for #4228, #4284)?
- `get_email_preferences.php`: `get_email_preferences()` is called with no properties when the site has no `email preferences` page (`get_email_preferences_screen()` in `includes/fn/forms.php`); `page_id` falls back to 0. Every unsubscribe link in a campaign mail reached it (#4228).
- `get_page.php`: an archived page, or one in the Recycle Bin (`pg_folder_binned()` sets `$folder_archived`), logs "access denied to archived page" before the 410 for a visitor without a session; the username passed to `log_activity()` is empty then (`log_activity()` takes `USER_USERNAME` itself when it is empty) (#4284).
- `get_page.php`, `guest` access: `$_SESSION['software']['guest']` is set only by `registration_entrance.php`; it is read with `empty()` first.

## Which `sessionusername` reads are safe?
- In `get_page.php` the private-page `log_activity()` calls and the membership query run only after a signed-in check; in `get_email_preferences.php` the user query sits under `pg_session_signed_in()`.
