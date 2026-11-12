---
name: bell-row
description: A bell row in the drawer: tag, dot, title; place, time, button, menu; a two-line excerpt; a Workspace row's short title ("Ada asked you") with its channel as the place
---

## What does a bell row carry?
- `pg_panel_get_notifications()` (`includes/panel/notifications.php`) adds three fields from pure functions in `includes/notifications.php`:
  - `module` = `pg_notification_module($action)`: shop (`new_order`, `order_return`, `out_stock`, `booking_refused`), erp (`erp_*`), content (`form_submited`, `new_comment`), team (`workspace`), system (the rest, a custom row too).
  - `day` = `pg_notification_day($timestamp, $now, $zone)`: `today`, `yesterday` or `earlier` in the site's zone; a future time is today; yesterday is found from noon, so a clock change does not skip it.
  - `button` = `pg_notification_button($action, $kind, $join)`: reply (`new_comment`; Workspace `mention`, `thread`, `guest_message`, `question_asked`, `mail_assigned`, `mail_customer`), approve (`approval_requested`, `approval_reminder`), join (`meeting_soon` with a call), show (the rest); '' for a custom row.
- `pg_notification_fold()` gives a fold's runs their head's `day`. `title` is the short title when there is one, else the sentence; `place` is a Workspace row's channel.

## What is a Workspace row's short title?
- `ws_bell_title($kind, $who, $place)` (`includes/workspace/notify.php`), called by `ws_bell_describe()`: "<sender> mentioned you", "<sender> asked you", "<sender> asked you to say you read it", "<sender> wrote" (guest), "<sender> added you to a channel", "<agent> answered you".
- The channel goes to line two: "#sales", or "A discussion in #sales" (`ws_inbox_place()`); a gone channel gives none. An MCP agent is `@alias`.
- Another kind, or no sender's name, keeps the sentence and no place; a site row keeps its title; the device push banner keeps the sentence (`notifications-menu.md`). Owner 2026-11-11, #8754.

## How is the row drawn?
- `pg_bell_row(row)` (`backend.src.js`), `li > .pg-bell-card`, with no column of the row's height:
  - `.pg-bell-line`: the tag `.pg-bell-tag[data-module]` as a small mark, the dot, the title (a button that opens the row; text on a custom row);
  - `.pg-bell-sub`: `.pg-bell-meta` (the place, "·", the time), then `.pg-bell-side` at the right: the one button `.pg-bell-act` (Reply, Show, Join, Approve) and "More actions";
  - `p.pg-bell-excerpt`, two lines at most (`line-clamp: 2`); the fold's "+N more".
- Without a place from the server: the details with `<br>` as " · ", else the sender when there is no excerpt.
- The dot is a button "Mark as read" (`pg_bell_read_button()`). The menu `.pg-bell-menu` holds "Mark as unread" (read rows) and Delete: a disclosure of its own, not a Bootstrap menu; it opens upwards when there is no room below.
- Day headings `li.pg-bell-day` (Today, Yesterday, Earlier); `pg_bell_filter_apply()` hides one with no shown row.

## Which keys work?
- Tab reaches the dot, the title, the button and the menu button. In the open menu the arrows, Home and End move; Escape closes it and returns to its button; a second Escape closes the drawer.
- Bootstrap takes menu keys on the document's capture phase, so `pg_bell_menu_key()` listens on `window`'s capture phase first.
- The dot moves the focus to the row's menu button; Delete to the next row's, or the one before, else the drawer's close button.

## Where do the tag colours come from?
- `--pg-bell-tag-{shop,erp,content,team,system}-{bg,fg}` in `backend.src.css` (light `:root`, dark `[data-bs-theme="dark"]`), copies of `.pg-dash-queue-tag[data-module]`; `tests/bell_row_test.php` holds them equal.

## Does the text wrap?
- The title wraps (`overflow-wrap: anywhere`, no ellipsis). Place and time run as one line of text: a long place wraps at its spaces, the time stays whole after it. Nothing scrolls sideways on a phone.

Tests: `tests/bell_row_test.php`, `tests/bell_drawer_test.php`. Built in #8722 (BP2), laid out for the drawer in #8783 (BP2b).
