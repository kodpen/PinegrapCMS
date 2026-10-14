---
name: settings-modal-layout
description: Settings modal layout and CSS pitfalls: three sizes, min-height 0 scroll chain, modal margin height, dvh, phone fullscreen max-width, drawer visibility.
---

## What is the layout at each width?
Same form at every width: list on the left, one pane beside it, and ONLY the pane scrolls.
| Width | Dialog | List |
|---|---|---|
| >= 992px | floating, screen visible at the sides | left, 15rem |
| 576-992px | full screen | left, 12rem |
| < 576px | full screen | drawer sliding over the pane |

## Which CSS rules must not be broken?
- Every flex item in the scroll chain carries `min-height: 0`. A flex item does not shrink below its content on the MAIN axis; in column direction `.pg-sm-main` grows to the height of all cards, the pane never scrolls and the save bar ends a thousand pixels below the screen (measured on a phone: 1921px content in an 812px dialog, Save unreachable). In row direction (desktop) `min-height: auto` is not the main axis, so the bug showed only on narrow screens.
- Dialog height must agree with Bootstrap's own margin: `--bs-modal-margin` is 1.75rem at >= 576px; content of `100vh - 3rem` overflows the window by 8px, the modal scrolls inside itself and the dialog leaves the top of the screen. The value is `3.5rem`.
- Write `100dvh` AFTER `100vh` and inside `@supports`: a phone browser's toolbar slides away and `100vh` keeps the taller value; `dvh` is live; a browser that does not know it stays on `vh`.
- On phones `modal-fullscreen-lg-down` alone is not enough: the dialog's `max-width: min(1180px, calc(100vw - 3rem))` beats Bootstrap's `max-width: none`, so the screen behind shows at the edge. The <= 991.98px branch writes `max-width: none`.
- The drawer never puts `visibility` in a timed transition: `visibility` is discrete, with a duration the change happens mid-way and if the transition never runs the drawer stays unreachable. Open: instant; close: at the end of the slide (`visibility 0s linear .18s`).
- A closed drawer must be `visibility: hidden`: an off-screen list otherwise receives focus and is read by screen readers.
- The list has its own surface (`--bs-secondary-bg`), not `--pg-tint`: that token is a 5% white wash, invisible on the theme's darkest colour, so the sidebar reads as "no background" and the dialog loses its shape. The active row returns to the pane's colour with `--bs-body-bg`, like a notch opened from list to pane.
- No search box in the sidebar: with all eight rows on screen, typing is an extra step, not a shortcut. `data-pgsearch` and `filter()` were removed; the `keywords` in `registry.php` stay because the command palette reads them.

Related: docs/memory/ui/settings-modal-architecture.md
