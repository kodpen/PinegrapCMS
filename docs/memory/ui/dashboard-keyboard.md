---
name: dashboard-keyboard
description: Dashboard keyboard and screen-reader access: Online Engagement rows, focusable System Status chips, focus kept across the refresh, '{var:1}%' figures, empty sentences
---

## How are Online Engagement's rows reachable by keyboard?
- A "Waiting for a reply" row is a `<button type="button">` reset with `border-0 bg-transparent w-100 text-start`; its boxes are spans, since a button holds only phrasing content.
- A roster row the reader may edit has its name as `<a href="edit_user.php?id=N" class="stretched-link">`. The row is `position-relative`, and the Start Chat control is a sibling `<button aria-label="Start Chat">` with `position-relative z-2` above the link, never nested in it. The avatar is `z-2` too, so its presence `title` still shows on hover; the avatar is `aria-hidden` (`contacts.php`), so Away is also written in the seen line (`Away · <relative time>`), the only other difference from Offline.
- Before, all three were `onclick` on a div or an `<i>`, so no keyboard reached them. Pinned by `tests/dashboard_keyboard_test.php`.

## Why does the System Status popover open on focus now?
- `welcome.php` delegates `.status-popover` from `document.body`. Bootstrap 6 binds focusin/focusout only when the parent's `trigger` lists `focus`, and it forces the child instance to `manual`, so the chip's own `data-bs-trigger` is ignored. The delegate is `"hover focus"`.
- A chip without an href was a `<div>` and could not take focus; it is a `<button type="button">` now (`button.pg-check` is already styled in backend.src.css).

## How does the refresh keep focus?
- `pg_widget_apply()` replaces a card's body when its HTML changes, and Online Engagement's relative times change every minute. `pg_widget_focus_save()` reads the focused element's `data-pg-focus-key` inside the card, and `pg_widget_focus_restore()` focuses the element with that key after the append (`preventScroll`). A control with no key is not restored. The keys are `chat-<id>`, `user-<id>` and `chat-with-<id>`.

## What does a System Status job do with focus and its report?
- `pg_health_job()` (`backend.src.js`) redraws the card, which removes the pressed button. In `settle()` it focuses the new `#<action>` button (`preventScroll`) when focus fell to the body or stayed in the card, and says the report through `window.pgAnnounce(text, assertive)`: two visually hidden regions made empty at load, written 150 ms later, since a panel inserted with its words is not read. Pinned by `tests/panel_live_status_test.php`, with the e-Document result boxes, the Site Settings filter count and the message dialog's close name.

## How are percentages printed on the dashboard?
- Through `lang(array('string' => '{var:1}%', 'vars' => …))`, which Turkish words as `%12`. Signs and arrows stay outside the template (`+%12`, `▲ %12`). The Visitor Summaries period switch takes the template from PHP as `T.percent` with a `{n}` placeholder. The System Status score puts its small `<span>%</span>` on the side the translated template puts it.
- The Visitor Summaries period cells carry `aria-pressed`, and the switch sets it beside `.is-active`.

## Which empty states changed?
- No sentence is "There is no {var:1} right now." with a noun dropped in any more: each card has a whole sentence (No orders yet., No open shopping carts., Nobody is online right now., No sales yet., Every product is in stock., No offers right now., No other currencies are set up.).
- Exchange rates shows its empty state when no row was printed (a base-only site printed none). Subscriptions shows one when all four licence rows are empty. Activity with no rows at all says "No activity recorded yet." instead of the per-kind note.

## What was not checked in a browser?
- Nothing was rendered (no panel run in a sandbox). Classes were checked against `assets/lib/bootstrap-6/css` only.

Related: docs/memory/ui/dashboard-hover-figures.md, docs/memory/ui/dashboard-card-figures.md, docs/memory/ui/dashboard-widget-files.md, docs/memory/ui/panel-accessible-names.md
