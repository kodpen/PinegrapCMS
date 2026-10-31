---
name: bell-refusals
description: What the bell panel shows when loading, marking unread or Delete All Notifications is refused or fails (no endless spinner, refused rows put back)
---

## What does the open bell show when get_notifications is refused?
- `get_notifications()` (`backend.src.js`) replaces the spinner with a `.pg-bell-failed` line (`role="alert"`) holding the server's `message` (e.g. "Your session expired. Reload the page and try again.") or `lang('An error occurred.')`, and puts back the unread badge it cleared before the request (nothing was marked read).
- A `keep_place` redraw (the unread poll, `notifications-menu.md`) keeps its old list and shows nothing on failure.
- Before, a refusal or an AJAX error only went to `console.log` and the open bell spun until a reload.

## What happens to a row whose edit is refused?
- `edit_notification()` hides a removed row but takes it out only after `success`; a refused or failed edit shows the row again with its old `new`/`viewed` classes and a danger `pgToast` with the reason (`pg_bell_refused()`).
- A refused 'Mark as unread' also runs a quiet `get_notifications(false, true)`, because its button removed itself on the click.
- Before, the row vanished while the notification stayed on the server and came back on the next redraw.

## And Delete All Notifications?
- A refusal or failure shows the danger toast; before, it was only logged and the click did nothing visible.

## Which strings can the script show?
- Only keys of the panel's run-time dictionary (`$translate` in `includes/fn/output.php`); the session sentences come translated in the server's `message`, the fallback is 'An error occurred.'.

Pinned in `tests/bell_and_edoc_refusal_test.php`. Related: docs/memory/ui/notifications-menu.md, docs/memory/core/json-endpoint-rules.md
