---
name: canvas-sample-other
description: What the designer canvas draws in comments, calendar, Google reviews, booking, accessibility statement, ads, menus and the language switcher, and a loop row's place (CS4c, #9299)
---

## Where do these kinds' samples come from?
- `_SD_REGION_SAMPLES` in `style_designer.src.js`: per widget kind (`_swKindOf(cfg)`, so `comments_recent` too), the values no other map has; `_sdSampleRecord(node)` returns it after the order, product and account records. It holds only the missing values, so the short label map and the comments' page words keep theirs.
- Comments: id 42, anchor `c-42`, date and time, rating 5 with its label and stars, depth, reply count, and the watcher sentence as `pg_comments_watcher_count_text()` writes it. The sample comments after the card keep their own values.
- Calendar and event view (`_sdEventSample()`): the Sample Event of `_SD_SHORT_LABEL_TOKENS`, 23 September 2026; month, year, view `monthly`, the empty message, summary, notes, the reservation message, and the event entity values. `__event_is_all_day` stays empty: the sample event runs 10:00 – 12:00.
- Google reviews, booking, accessibility statement (the site name from `sdDesign.siteWords`, `canvas-sample-account.md`), ad region (a slideshow: `carousel`, 5000, `true`), menus, language switcher (English, active).

## What do the rows after a main loop's card draw?
- `_SD_REGION_LOOP_ROWS`: Turkish and German after English (not active), a second review, a second ad. Other kinds copy the card, as before.

## How does a row know its place?
- `__loop_index`, `__loop_count`, `__loop_is_first|last|odd|even`: the card is row 1 of the card and its sample rows (`_sdLoopCardPosition(node)`); `_sdLoopSampleEls()` gives each sample row its own place (`_sdLoopPosition(i, n)`). So a "first row only" condition shows on the card alone; before, every row read the card.
- A test that cuts `_displayValueForBinding()` or `_sdListSampleRows()` out of the file stubs `_SD_LOOP_POSITION_TOKENS` and `_SD_REGION_LOOP_ROWS`.

## How is it tested?
- `tests/designer_canvas_other_samples_test.php`: the row places, the stars against the rating, the language rows, that only the all-day flag is empty.

## What is left?
- `canvas-sample-gaps-other.md`.
