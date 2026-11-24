---
name: bug-hunt-areas-notifications-bell
description: The bug hunt of the panel's bell and the mails it sends (2026-11-24, #11438): the seven faults fixed, what was read clean, what was judged decided
---

## Which area was hunted?
- Architect (2026-11-24, area 40): the panel's bell and the mails it sends: a notice to someone who lost the right to see its subject, the unread count against the list, one notice per event, read across devices, a deleted record, the daily summary's time zone.
- Read: `includes/notifications.php` (ladder, unread rows, read marks, display, day and short time), `includes/panel/notifications.php` (count, list, edit, Delete All, push_pending), `includes/push.php` (enqueue, still relevant, queue run), `includes/api/resources/account.php` (GET /notifications, count, read, unread), `ws_notify()` and `ws_bell_*` in `includes/workspace/notify.php`, `create_notification()` and its callers, `software_update_check.php`, the poll in `backend.src.js`.

## What did the hunt's PR fix?
- A Workspace row stayed on the bell, in the badge and on the phone after its person lost its line (taken out of a private channel, a task no longer theirs), its kept title naming the channel (`pg_notification_ws_line_open()`, `workspace/automation-flows-bell.md`).
- A queued device banner woke somebody whose right to its subject was taken while it waited (`pg_push_queue_still_relevant()`, `core/push-disabled-account.md`).
- A line the Workspace read by hiding the message, an acknowledgement or a line out of reach stayed unread on the bell (`ws_bell_sync_read()`, `ui/bell-read-rule.md`).
- The open drawer kept rows read on another device unread; the poll redrew only on a rise (`ui/notifications-menu.md`).
- A marketplace order announced an already sold-out product again (`commerce/order-completion-stock-and-spots.md`).
- "Software update available" came back unread with every check and every opening of the Software Updater (`platform/update-check-errors.md`).
- A row's day heading and short time read the site's zone, its title the reader's (`ui/bell-row-time-and-tint.md`).

## What was read clean?
- The badge and the list ask the same ladder on the same columns; Mark all as read, Delete All and the API touch only rows the caller sees.
- Read marks are per person in `notification_reads`, so a row read on one device is read on all; the API's read and unread answer the new count.
- A deleted order, submission or comment opens its screen's not-found message with a way back; deleting a comment or a submission takes its rows.
- A WhatsApp message Meta sends again finds its wamid taken and is not announced twice; a web order's `out_stock` is guarded.
- The ERP overdue digest, operations alerts and the Workspace's morning e-mail count their day in the site's zone, which init.php sets.

## What was judged decided, not a fault?
- The badge counts each run of a folded flow line; the list folds them (`ui/notifications-one-inbox.md`).
- A failed update check takes the "update available" row away, as before (`platform/vendor-api-update.md`).
