---
name: notifications-menu
description: The bell's drawer #notifications at the right edge: opened by the bell, Ctrl+Q, pgBellOpen(); head, events, focus; only the list scrolls; the poll redraws only on a rise
---

## What is the bell's panel?
- `<dialog class="drawer drawer-end pg-bell-drawer" id="notifications">` (`$output_bell_drawer`, `includes/fn/output.php`), full height, `--pg-drawer-width: min(30rem, 100vw)`: 30rem, the whole width on a phone. Owner 2026-11-11, #8754 (`workflow/decisions-made-panel-4.md`); built in #8783 (plan step BP2b).
- Printed after the header, not inside it: Bootstrap hides a drawer that sits inside a `.navbar` when the window is resized.
- Head `.pg-bell-head`: the heading "Notifications", "Mark all as read" (shown while a row is unread), a menu holding "Delete All Notifications" (under All with two rows or more), the close button. Then the filter chips, the list `.pg-bell-scroll`, the push line `#push_row`/`#push_hint` at the foot.
- Only the list scrolls: `.pg-bell-body` `overflow: hidden`, `.pg-bell-scroll` `overflow-y: auto; min-height: 0`. The old 26rem `.menu` box is gone.

## How is it opened and closed?
- The bell carries `data-bs-toggle="drawer" data-bs-target="#notifications"`; it has no click handler of its own.
- Ctrl+Q (window `keydown`, `case 'q'`) calls `pgUI.drawer.toggle('#notifications')` in a `setTimeout` (Firefox opened its find bar otherwise). `pgBellOpen()` shows it, or only applies the filter when it is open.
- Escape and a click outside close it (Bootstrap's defaults: no `data-bs-backdrop`, no `data-bs-keyboard`). A row's or the head's menu keeps its Escape: `pg_bell_menu_key()` listens on `window`'s capture phase (`bell-row.md`). Closing gives the focus back to the bell.

## Where are its events heard?
- On the drawer (`pgUI.drawer.on('#notifications', …)`): `show` hides the head's actions till the list's counts arrive, adds the bell's `show` class and loads the list once (`get_notifications()`), and `pg_push_refresh()` runs; `hidden` drops the `show` class, scrolls the list to the top, keeps the filter and closes an open row menu.
- `shown` focuses the close button: a shut Bootstrap 6 drawer is `visibility: hidden` with a transition, so `showModal()` finds nothing to focus and left the focus on the body (#8783).
- The 20 s poll reads `pgUI.drawer.isOpen('#notifications')`. `tests/bell_drawer_test.php` pins the markup, the opening rules and the scrolling.

## When does the 20 s unread poll redraw the open list?
- Only when the unread count rises above `$unreadsCallback`, a top-level count that holds the count the open list shows: every drawing of the list sets it to its unread rows, every poll to the server's count. Opening reads nothing (`bell-read-rule.md`).
- It used to be reset to 0 every poll, so with anything unread the open list was emptied, scrolled to the top and lost the focused row every 20 s.
- The poll calls `get_notifications(true)` (`keep_place`): the old list stays until the new one arrives, then the scroll and the focus on the same `li[notification-id]` row come back. `tests/panel_timers_test.php` pins it.

## Why is a bell title one line?
- Look A (DL3, #8897): the title is short ("New order #1044", `bell-row.md`) and stands on one line with the time at its right, ending in an ellipsis when it does not fit; the text under it carries the rest in two lines. Before, long sentences as titles wrapped (#3549 era), since `nowrap` cut 44–50 character sentences.
- The server sends titles escaped (`pg_notification_display()`), so the script inserts them as they come. `tests/mail_queue_full_text_test.php` pins it.

## Why does a device push banner decode its title?
- The service worker shows `item.title` as text, but bell titles are escaped, so `&` arrived as `&amp;`. `pg_panel_push_pending()` sends `pg_panel_push_title()` (strip tags, decode, trim). The push banner keeps the whole sentence, not the drawer's short title (`bell-row.md`). `tests/push_banner_title_test.php` (#3549).
