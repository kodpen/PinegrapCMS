---
name: guest-session-flag
description: $_SESSION['software']['guest'] exists only after a visitor picks Guest; every guest-access check reads it with ?? false
---

## Where is the guest flag set?
- `registration_entrance.php` sets `$_SESSION['software']['guest'] = true` when a visitor chooses to go on as a guest. Until then the key does not exist, and in `get_file.php` on a cron run there is no session at all.

## How is it read?
- Always `($_SESSION['software']['guest'] ?? false)`: `get_file.php` (guest folder), `get_photo_gallery.php` (four checks), `check_view_access()` in `includes/authentication.php`; `get_page.php` uses `empty()`. A bare read raised an "Undefined array key" warning for every visitor who had not picked Guest (fixed together with `get_file.php` refusing an array `?name[]=`, which threw a TypeError in `escape()`). `tests/guest_session_flag_test.php` guards both.
