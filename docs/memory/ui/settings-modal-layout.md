---
name: settings-modal-layout
description: Settings modal layout and CSS pitfalls: three sizes, min-height 0 scroll chain, dialog margin height, dvh, phone fullscreen width, drawer visibility.
---

## What is the layout at each width?
Same form at every width: list on the left, one pane beside it, and ONLY the pane scrolls. The dialog is `<dialog class="dialog pg-settings-modal dialog-xl pg-dialog-centered lg-down:dialog-fullscreen pg-sm">` (`includes/settings/modal.php`).
| Width | Dialog | List |
|---|---|---|
| >= 1024px | floating, centred, screen visible at the sides | left, 15rem |
| 576-1024px | full screen | left, 12rem |
| < 576px | full screen | drawer sliding over the pane |

## Which CSS rules must not be broken?
- Every flex item in the scroll chain carries `min-height: 0`. A flex item does not shrink below its content on the MAIN axis; in column direction `.pg-sm-main` grows to the height of all cards, the pane never scrolls and the save bar ends a thousand pixels below the screen (measured on a phone: 1921px content in an 812px dialog, Save unreachable). In row direction (desktop) `min-height: auto` is not the main axis, so the bug showed only on narrow screens.
- Dialog height must agree with Bootstrap's own margin: `--bs-dialog-margin` is 1.75rem (the dialog's max-height is the window minus it twice); content of `100vh - 3rem` overflows. The value is `min(860px, calc(100vh - 3.5rem))`.
- Write `100dvh` AFTER `100vh` and inside `@supports`: a phone browser's toolbar slides away and `100vh` keeps the taller value; `dvh` is live; a browser that does not know it stays on `vh`.
- Width is `--bs-dialog-width: min(1180px, calc(100vw - 3rem))`. Below 1024px `lg-down:dialog-fullscreen` sets `width: 100%; max-width: none` itself; the panel's `max-width: 1023.98px` branch sets `height: 100%` and drops the border and radius (panel CSS in `@layer custom` beats the component's height).
- The phone list drawer (`.pg-sm-side`, the panel's own CSS, not Bootstrap's `.drawer`) never puts `visibility` in a timed transition: `visibility` is discrete, with a duration the change happens mid-way and if the transition never runs the drawer stays unreachable. Open: instant; close: at the end of the slide (`visibility 0s linear .18s`).
- A closed drawer must be `visibility: hidden`: an off-screen list otherwise receives focus and is read by screen readers.
- `drawer(false)` (Escape, the scrim, choosing a row) puts the focus on the list button `#pg_settings_modal_menu` when it was inside `#pg_settings_modal_side` and the width is the drawer's (`max-width: 575.98px`); a row turning `visibility: hidden` would otherwise drop it. Wider, the chosen row keeps the focus (`tests/panel_drawer_focus_test.php`).
- At that width focus leaving `#pg_settings_modal_side` (Tab past the last row) calls `drawer(false)` (`sideLostFocus()` in `wire()`); before, Tab reached the list button the drawer covers and the pane fields under the scrim. A `focusout` with no `relatedTarget` is read on the next turn; `<body>` counts as staying.
- The list has its own surface (`--pg-bg-2`), not `--pg-tint`: that token is a 5% white wash, invisible on the theme's darkest colour, so the sidebar reads as "no background" and the dialog loses its shape. The active row returns to the pane's colour with `--pg-bg-body`, like a notch opened from list to pane.
- No search box in the sidebar: with all eight rows on screen, typing is an extra step, not a shortcut. `data-pgsearch` and `filter()` were removed; the `keywords` in `registry.php` stay because the command palette reads them.

Related: docs/memory/ui/settings-modal-architecture.md
