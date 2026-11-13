---
name: bell-row
description: A bell row in look A (#8897): tone tile, one-line title and time, two lines of text, a button only when asked, the unread dot, quick actions on hover; a fold as gathered lines
---

## What does a bell row carry?
- `pg_panel_get_notifications()` (`includes/panel/notifications.php`) adds three fields from pure functions in `includes/notifications.php`:
  - `module` = `pg_notification_module($action)`: shop (`new_order`, `order_return`, `out_stock`, `booking_refused`), erp (`erp_*`), content (`form_submited`, `new_comment`), team (`workspace`), system (the rest, a custom row too).
  - `day` = `pg_notification_day($timestamp, $now, $zone)`: `today`, `yesterday` or `earlier` in the site's zone; a future time is today; yesterday is found from noon, so a clock change does not skip it.
  - `button` = `pg_notification_button($action, $kind, $join)`: reply, approve, join (`meeting_soon` with a call), show (the rest); '' for a custom row.
- `title` is the short title when there is one, else the sentence; `details` is the brief text when there is one; `place` is a Workspace row's channel. `pg_notification_fold()` gives a fold's runs their head's `day`.

## What does a title say?
- A short title of what happened and with what (`bell-titles.md`).

## How is the row drawn?
- `pg_bell_row(row, draw)` (`backend.src.js`) on the recipes (`look-type-and-recipes.md`): `li > .pg-bell-card.pg-list-row` (`.is-read` dims a read row):
  - `pg_bell_tile()`: `.pg-icon-tile.pg-tone-*`, shop teal, erp amber, content rose, team blue, system gray; a warning (and `out_stock`) amber, an error rose. The glyph names the action; a Workspace row brings its own.
  - head: the title `.pg-list-row-title` (a button that opens the row; one line, ellipsis), `pg_bell_quick()` and the time. The quick actions ("Mark as read" check, "More actions") replace the time on hover or focus; with `(hover: none)` (a phone) only the menu button stays beside the time.
  - `.pg-list-row-text`, two lines: the excerpt, else the details (`<br>` as " · "), else the sender.
  - `.pg-list-row-actions` only when the row asks: an in-place act (`bell-actions.md`), Reply, Approve, Join, or Show on an unread warning or error row; plus the place. The first unread asking row's button is filled (`draw.fill`), the rest quiet. Every other row opens from its title.
  - the unread dot `.pg-unread-dot.pg-bell-dot` at the right, hidden on a read row.
- The menu `.pg-bell-menu` holds "Mark as read" (unread rows), "Mark as unread" (read rows) and Delete: a disclosure of its own, not a Bootstrap menu; it opens upwards when there is no room below.
- Day headings `li.pg-bell-day` (Today, Yesterday, Earlier), 12 px, sentence case; `pg_bell_filter_apply()` hides one with no shown row.

## How is a fold drawn?
- A flow's runs (`ws_bell_fold_key()`) fold under the newest: its title carries the count `.pg-count` beside it, and `.pg-gather-toggle` "Show N more" / "Hide" opens and closes the runs.
- Each older run stays a `li` of its own (`pg_bell_run()`: read, unread and Delete work as before), drawn as one `.pg-gather-line` lined up under the head's text: the time in bold, what it says, the dot; its quick actions on hover or focus.
- The toggle stays above the open lines (the sheet draws "Hide" under them).

## Which keys work?
- Tab reaches the title, "Mark as read", the menu button, the row's button. In the open menu the arrows, Home and End move; Escape closes it and returns to its button; a second Escape closes the drawer.
- Bootstrap takes menu keys on the document's capture phase: `pg_bell_menu_key()` listens on `window`'s first.
- "Mark as read" moves the focus to the row's menu button; Delete to the next row's, or the one before, else the drawer's close button.

Tests: `tests/bell_row_test.php`, `tests/bell_drawer_test.php`, `tests/bell_read_rule_test.php`. BP2 #8722, BP2b #8783, look A #8897 (DL3).
