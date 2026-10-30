---
name: guided-tour
description: The guided tour engine (assets/js/pg_tour.js, pg_tour.css): who uses it, how steps are counted, Back at the start, how a step is read out, where focus goes at the end, the bubble's size
---

## Who runs on the engine?
- `assets/js/pg_tour.js` runs every panel tour: the frame tour on every screen (`includes/fn/tour.php`), the File and Catalog Manager tours (`view_folders.php`) and the Workspace tour (`workspace_tour.js`). Panel only. It is served unbundled through `pg_tour_assets()`: no `.min.js`, no `build_js.php` step.
- A screen registers its steps (`PgTour.register`); the engine knows nothing about any screen. `tests/tour_engine_test.php` pins the engine rules below; `tests/return_key_buttons_test.php` pins the Enter guard in `onKey` (`return-key-default-button.md`).

## How are steps counted?
- `shownSteps()` lists the steps that will show: not `ruledOut()` (skipIf), and for a step without `before()` its target on screen (`targetReady()`); the current step always counts. The count (`labels.of`), the dots and the done label go by that list.
- Before #3284 the count used `steps.length`: the frame tour, which always passes over one of its two menu steps, read "1, 2, 4 … 9 of 9", and with the icon-only menu or on a phone the explorer tours opened at "3 of 8" / "3 of 9".

## What does Back do on the first shown step?
- Nothing: Back is hidden when no earlier step can show, and `move(-1)` returns there (ArrowLeft too). `goTo()` turns forward (`direction = 1`) when it walks past index 0.
- Before, `goTo(-1)` clamped to 0, step 0 was ruled out, and it called `goTo(-1)` again for ever: a RangeError after `leave()` had already run.

## How is a step read out?
- The bubble is a `role=dialog` named by the title and described by the count and the text (`aria-describedby`). Focus moving onto Next on the first step reads both.
- From the second step on focus is already on Next, so nothing moves: a visually hidden polite `role=status` in the bubble gets "count. title. text" after the step is painted (emptied first in `show()`).
- So a step names a control by its label (`notice-button-names.md`).

## Where does focus go when the tour ends?
- `focusBack()`: to the element that started it, unless that is hidden or gone. The Interface tour row (account menu) and Watch the tour (explorer More menu) sit in menus that close on the click, so focus goes to the `[data-bs-toggle="menu"]` toggle of the closest `.menu`'s parent. Nothing usable: the focused bubble button is blurred. Bootstrap 6 Menu never returns focus to its toggle by itself.

## How does the bubble fit a short screen?
- `max-height: calc(100dvh - 24px)` (20px under 576px, where it sits at the bottom), `box-sizing: border-box`, a flex column: count, title and text scroll in `.pg-tour-body`, the foot (Skip, dots, Back, Next) keeps its height. No overflow on `.pg-tour-bubble` itself: it would clip `.pg-tour-arrow`, which sits outside the edge.
- `centre()` clamps left and top to the 12px edge; the old centred paths gave a negative top at 320x256.

## How is step text that scrolls reached from the keyboard?
- `fitBody()`, run by every `paint()` (resize, scroll): while `.pg-tour-body` overflows it is `tabindex=0`, `role=region`, `aria-labelledby=pg_tour_title`; when it fits these go and focus on it moves to Next. `onKey`'s Tab ring takes `[tabindex="0"], button:not([disabled])`, so the text comes before Skip. Before, the ring held only buttons and long text on a short screen could not be scrolled from the keyboard.

## What colour is Next?
- `.pg-tour-primary` text is `--pg-on-accent` on `--pg-primary`, like every filled panel control; white on `#64b5f6` was about 2.2:1.
- `veil` and `bubble` keep `data-pg-menu-hold` (`main-menu-panel-scripts.md`).

## What marks the target in forced colours?
- The ring and the dim are box-shadows on `.pg-tour-hole`, which Windows High Contrast removes; under 576px the arrow is hidden too. `@media (forced-colors: active)` gives the hole `outline: 3px solid Highlight` (`tests/forced_colors_widgets_test.php`).
