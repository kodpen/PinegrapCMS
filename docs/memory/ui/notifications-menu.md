---
name: notifications-menu
description: The bell's drawer #notifications at the right edge: opened by the bell, Ctrl+Q, pgBellOpen(); head, events, focus; only the list scrolls; the poll redraws when the count moves
---

## What is the bell's panel?
- `<dialog class="drawer drawer-end pg-bell-drawer" id="notifications">` (`$output_bell_drawer`, `includes/fn/output.php`), full height, `--pg-drawer-width: min(30rem, 100vw)`: 30rem, the whole width on a phone. Owner 2026-11-11, #8754 (`workflow/decisions-made-panel-4.md`); built in #8783 (plan step BP2b).
- Printed after the header, not inside it: Bootstrap hides a drawer that sits inside a `.navbar` when the window is resized.
- Head `.pg-bell-head`: the heading, the "N new" pill and three icon buttons, Search, More actions, Close (`bell-head.md`).
- Then the filters (`bell-filters.md`), the list `.pg-bell-scroll`, the list's states and the foot `#push_row`/`#push_hint` (`bell-states-and-foot.md`).
- Only the list scrolls: `.pg-bell-body` `overflow: hidden`, `.pg-bell-scroll` `overflow-y: auto; min-height: 0`.
- Its bar is the panel's thin one (BR3 #11154), as `.card.widget`'s: `::-webkit-scrollbar` 6 px with `--pg-scrollbar-thumb`; Firefox gets `scrollbar-width: thin` only under `@supports not selector(::-webkit-scrollbar)`, since `scrollbar-color` switches Chromium off its own rules. Before, the browser's bar (15 px on Windows). Headless Chromium on Linux draws none: measure it with `getComputedStyle(list, '::-webkit-scrollbar').width`.

## How is it opened and closed?
- The bell carries `data-bs-toggle="drawer" data-bs-target="#notifications"`; it has no click handler of its own.
- Ctrl+Q (window `keydown`, `case 'q'`) calls `pgUI.drawer.toggle('#notifications')` in a `setTimeout` (Firefox opened its find bar otherwise). `pgBellOpen()` shows it, or only applies the filter when it is open.
- Escape and a click outside close it (Bootstrap's defaults, no `data-bs-backdrop`/`data-bs-keyboard`). Escape goes to an open menu first (`pg_bell_menu_key()` on `window`'s capture phase, `bell-row.md`), then to an open search (`pg_bell_search_key()`, registered after it, skips an event already `defaultPrevented`), then to the drawer. Closing gives the focus back to the bell.

## Where are its events heard?
- On the drawer (`pgUI.drawer.on('#notifications', …)`): `show` disables the head menu's items and hides the pill till the list's counts arrive, adds the bell's `show` class and loads the list once (`get_notifications()`), and `pg_push_refresh()` runs; `hidden` drops the `show` class, scrolls the list to the top, puts back the kept choice, closes and clears the search and closes an open row menu.
- `shown` focuses the close button: a shut Bootstrap 6 drawer is `visibility: hidden` with a transition, so `showModal()` finds nothing to focus and left the focus on the body (#8783).
- The 20 s poll reads `pgUI.drawer.isOpen('#notifications')`. `tests/bell_drawer_test.php` pins the markup, opening and scrolling.

## When does the 20 s unread poll redraw the open list?
- When the unread count differs from `$unreadsCallback`, a top-level count that holds the count the open list shows: each drawing sets it to the list's unread rows, each poll to the server's count. A rise is something new; a fall is rows read on another device or tab, down to none (#11438). A change made in this list polls with `editcallback_check`, which never redraws. Opening reads nothing (`bell-read-rule.md`).
- Before #11438 only a rise redrew it: rows read on the phone kept their unread dot in the open drawer, and at a count of 0 nothing was redrawn.
- It used to be reset to 0 every poll, so with anything unread the open list was emptied, scrolled to the top and lost the focused row every 20 s.
- The poll calls `get_notifications(true)` (`keep_place`): the old list stays until the new one arrives, then the scroll and the focus on the same `li[notification-id]` row come back. `tests/panel_timers_test.php` pins it.
