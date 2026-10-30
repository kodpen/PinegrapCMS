---
name: notifications-menu
description: Header notifications panel (#notifications, bell #notification_toggle): one Menu on the bell, Ctrl+Q, events on the toggle (#2578), poll redraws the open list only on a rise; titles wrap, no ellipsis
---

## How is the panel opened?
- The bell `#notification_toggle` (`includes/fn/output.php`) carries `data-bs-toggle="menu"`, `data-bs-display="static"` and `data-bs-target="#notifications"`; the `.menu` box is placed by its own classes (`position-fixed end-0 top-0`).
- Ctrl+Q (`backend.src.js`, window `keydown`, `case 'q'`) calls `notification_toggle.click()` in a `setTimeout` (Firefox opened its find bar otherwise).

## Why not pgUI.menu.toggle('#notifications')?
- Under v6 that made a second Menu with the `.menu` box as its toggle: it ignored the bell's static display, ran Floating UI and wrote inline position, left, top and margin that nothing cleared, so the panel stayed misplaced (for the bell too) until a reload, and its `aria-expanded` on the box went stale.

## Where are the menu events heard?
- v6 fires `shown/hidden.bs.menu` on the toggle, not on the box: the shown/hidden listeners (`get_notifications(true)`, `pg_push_refresh()`, the bell's `show` class) sit on `#notification_toggle`, and the refresh checks in the unread poll read `pgUI.menu.isOpen('#notification_toggle')` (`aria-expanded`).
- One list refresh per open (the shown handler); the bell's click handler refreshes when it closes the panel. `tests/bs6_inline_feedback_test.php` pins both.

## When does the 20 s unread poll redraw the open list?
- Only when the unread count rises above `$unreadsCallback`, a top-level count that survives across polls and drops to 0 when `get_notifications(true)` marks everything read.
- It used to be reset to 0 at the start of every poll, so with anything unread (one 'Mark as unread' is enough, read state is per person) the open list was emptied, showed a spinner, scrolled to the top and lost the focused row every 20 s.
- The poll calls `get_notifications(false, true)`: the old list stays until the new one arrives, then the scroll position and the focus on the same `li[notification-id]` row come back. `tests/panel_timers_test.php` pins it.

## Why does a bell title wrap instead of ending in "…"?
- The panel is 350px wide and `.notification-title` carries `nowrap` + `ellipsis` in `backend.src.css`; titles of 44–50 characters ("Congratulations! There is a new successful order.") were cut even on desktop, the full text only in a hover `title`.
- The non-custom title is now `<small class="notification-title text-wrap text-break w-100">` with no `title`, like the custom branch (`text-wrap w-100`). Bootstrap 6 utilities sit in a later `@layer` than `custom`, so `text-wrap` beats the panel rule's `white-space: nowrap` and the ellipsis never shows. The server sends titles already escaped (`pg_notification_display()`: `h()` on stored text, `lang()` literals), so the script inserts them as they come.
- Mail queue (`mail_queue.php`): the e-mail and SMS "Last error" cells and the SMS body print the whole text (an error is stored up to 500 characters) in a `text-wrap text-break` cell of at most 28rem, no 80-character `truncate()` and no hover `title`. `tests/mail_queue_full_text_test.php` pins both.

## Why does a device push banner decode its title?
- The service worker shows `item.title` as text (`sw.js` `showNotification()`), but `pg_notification_display()` returns bell titles escaped, so a Workspace mention or custom title with `&` or `Ali'nin` arrived as `&amp;` / `&#039;`. `pg_panel_push_pending()` sends `pg_panel_push_title()` (strip tags, decode, trim, like `pg_notification_body()`); the display escaping stays for the bell. `tests/push_banner_title_test.php` (#3549).
