---
name: front-visitor-missing-keys
description: Front-end paths a visitor without a session or page properties reaches must not read session or property keys unguarded: the warning prints before the DOCTYPE where display_errors is on
---

## Why does an unguarded key matter on a front-end path?
- An E_WARNING goes to `data/temp/php_errors.log` on every request, and where `display_errors` is on it prints the server path before `<!DOCTYPE html>`: the page drops to quirks mode and the long path widens it past a phone's width. Guard with `isset()` / `??` (PHP 7.1: `??` is fine, `??=` is not).

## Which reads were guarded (PR #4305, issues #4228, #4284)?
- `get_email_preferences.php`: `get_email_preferences()` is called with no properties when the site has no `email preferences` page (`get_email_preferences_screen()` in `includes/fn/forms.php`); `page_id` falls back to 0. Every unsubscribe link in a campaign mail reached it (#4228).
- `get_page.php`: an archived page, or one in the Recycle Bin (`pg_folder_binned()` sets `$folder_archived`), logs "access denied to archived page" before the 410 for a visitor without a session; the username passed to `log_activity()` is empty then (`log_activity()` takes `USER_USERNAME` itself when it is empty) (#4284).
- `get_page.php`, `guest` access: `$_SESSION['software']['guest']` is set only by `registration_entrance.php`; it is read with `empty()` first.

## Which form handlers' refusals were guarded (PR #11061)?
- `order_form.php`, `custom_form.php`, `add_comment.php`, `add_or_remove_watcher.php` and `reserve_calendar_event.php` pass `$_SESSION['sessionusername'] ?? ''` to `log_activity()` in every refusal a signed-out visitor reaches.
- That covers a private order or custom form, a closed or sign-in-only comment thread, the watcher form, every reservation refusal, and an offer code a guest's custom form makes. Before, each refused post logged `Undefined array key "sessionusername"` (also after a session expired).
- `tests/visitor_form_handlers_test.php` names the guarded refusals by their log text.

## Which `sessionusername` reads are safe?
- In `get_page.php` the private-page `log_activity()` calls and the membership query run only after a signed-in check; in `get_email_preferences.php` the user query sits under `pg_session_signed_in()`.
- The watcher's view and management refusals and the custom form's auto-login line run after a sign-in check.
