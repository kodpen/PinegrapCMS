---
name: bell-refusals
description: What the bell panel shows when loading, marking read or unread or Delete All Notifications is refused or fails (the failed state with Try again, refused rows put back)
---

## What does the open bell show when get_notifications is refused?
- `get_notifications()` (`backend.src.js`) removes the skeleton rows and shows the failed state `.pg-bell-failed` (`role="alert"`, BR3 #11154, `bell-states-and-foot.md`): a rose tile, "Notifications could not be loaded", the server's `message` (e.g. "Your session expired. Reload the page and try again.") or `lang('An error occurred.')`, and "Try again", which loads the list again (the focus waits on Close). The unread badge is left as it is: drawing the list reads nothing (`bell-read-rule.md`).
- A `keep_place` redraw (the unread poll, `notifications-menu.md`) keeps its old list and shows nothing on failure.
- Before, a refusal or an AJAX error only went to `console.log` and the open bell spun until a reload; then (to BR2) a red line with no way to try again.

## What happens to a row whose edit is refused?
- `edit_notification()` hides a removed row but takes it out only after `success`; a refused or failed edit (remove, `mark_read`, `mark_unread`, `mark_all_read`) shows every row it touched again with its old read state (`pg_bell_row_state()`) and a danger `pgToast` with the reason (`pg_bell_refused()`).
- Before, the row vanished while the notification stayed on the server and came back on the next redraw.

## And Delete All Notifications?
- A refusal or failure shows the danger toast; before, it was only logged and the click did nothing visible.

## Which strings can the script show?
- Only keys of the panel's run-time dictionary (`$translate` in `includes/fn/output.php`); the session sentences come translated in the server's `message`, the fallback is 'An error occurred.'.

Pinned in `tests/bell_and_edoc_refusal_test.php`. Related: docs/memory/ui/notifications-menu.md, docs/memory/core/json-endpoint-rules.md
