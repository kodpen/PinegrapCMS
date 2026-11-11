---
name: bell-row
description: A bell row drawn as the dashboard's Next up row: module tag, title, second line, one button, the Mark as read dot, the row menu, day headings; module, day and button from pure functions
---

## What does a bell row carry?
- `pg_panel_get_notifications()` (`includes/panel/notifications.php`) adds three fields, each from a pure function in `includes/notifications.php`:
  - `module` = `pg_notification_module($action)`: shop (`new_order`, `order_return`, `out_stock`, `booking_refused`), erp (every `erp_*`), content (`form_submited`, `new_comment`), team (`workspace`), system (everything else, a custom row too).
  - `day` = `pg_notification_day($timestamp, $now, $zone)`: `today`, `yesterday` or `earlier` in the site's zone (`init.php` sets it as PHP's default). A time after now is today; yesterday is found from noon, so a clock change does not skip it.
  - `button` = `pg_notification_button($action, $kind, $join)`: reply (`new_comment`; Workspace `mention`, `thread`, `guest_message`, `question_asked`, `mail_assigned`, `mail_customer`), approve (`approval_requested`, `approval_reminder`), join (`meeting_soon` with a call), show (the rest); '' for a custom row.
- `pg_notification_fold()` gives a fold's runs their head's `day`, so they never open a heading of their own.

## How is the row drawn?
- `pg_bell_row(row)` (`backend.src.js`): one grid, as `.pg-dash-queue-row`: the tag (`.pg-bell-tag[data-module]`, 3.75rem), the text, then the side.
- Text: the title (a button that opens the row; plain text on a custom row), one second line `.pg-bell-sub` (the description, else the details with `<br>` as " · ", else the sender, then the time), the fold's "+N more" link.
- Side: the one button `.pg-bell-act` named by the job (Reply, Show, Join, Approve), the unread dot, the menu button ("More actions", `bi-three-dots`).
- The dot is a button named "Mark as read" (`pg_bell_read_button()`); a read row keeps an empty place so the columns stay put.
- The menu (`.pg-bell-menu`) holds "Mark as unread" (read rows only) and Delete. It is a small disclosure of its own, not a nested Bootstrap menu, so it never closes the bell; it opens upwards when the list has no room below.
- Day headings `li.pg-bell-day` (Today, Yesterday, Earlier) stand before the first row of each day; `pg_bell_filter_apply()` hides one with no shown row.
- The two full-width lines under each row ("Go to the relevant page", the "Mark as unread" box) are gone.

## Which keys work?
- Tab reaches the title, the button, the dot and the menu button in that order.
- In the open menu: the arrows, Home and End move; Escape closes it and returns to the menu button. The handler sits on `#notifications` and stops these keys, so Bootstrap's menu handler on the document does not close the bell.
- The dot moves the focus to the row's menu button (the dot is gone once read); Delete moves it to the next row's menu button, or the one before.

## Where do the tag colours come from?
- `--pg-bell-tag-{shop,erp,content,team,system}-{bg,fg}` in `backend.src.css`, light under `:root`, dark ink under `[data-bs-theme="dark"]`. They copy `.pg-dash-queue-tag[data-module]` of `pg_dash.src.css` (the bell is outside `.pg-dash`); System is the dashboard's neutral fill with `--pg-fg-2`.
- `tests/bell_row_test.php` holds the hues equal: a dashboard colour change fails it until the bell's token follows.

## Does the title still wrap?
- Yes: `.pg-bell-title` has `white-space: normal` and `overflow-wrap: anywhere`, no ellipsis and no hover `title` (`notifications-menu.md`). At phone width the tag stands over the text, as on the dashboard; nothing scrolls sideways.

Pinned in `tests/bell_row_test.php`. Built in #8722 (plan step BP2).
