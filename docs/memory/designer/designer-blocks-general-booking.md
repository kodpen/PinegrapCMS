---
name: designer-blocks-general-booking
description: The general booking family (booking- prefix, Services & Booking group): weekly class timetable and arrival windows, what each draws, its rules and test
---

## Where does it live?
- `assets/js/style_designer_blocks_booking.js` ([[designer-block-family-files]]): prefix `booking-` and no `group`, so its blocks join the core's "Services & Booking". The core's `booking-cta` and `booking-split` keep the single booking call. Which shared section each general block covers: [[designer-blocks-general]].

## Which blocks does it hold?
- `booking-week` (added in step `gym-1`): an `ol` of seven days (a column each from md up, scrolling sideways as a named region, stacked on a phone), each an `h3` and its classes as `pg-week-slot` (start `<time>`, name, who · where · length, Book). Header fades, days rise (stagger 80). For a gym, a yoga or dance studio, a pool.
- `booking-windows` (added in step `homeservices-1`): eyebrow, `h2` "Choose when we come", a line; three window cards (`pg-window`): a round icon (`aria-hidden`), the name as `h3`, start and end as `<time>`s in tabular figures, the hours drawn on the working day 08:00–19:00 (`pg-window-day`, `aria-hidden`, `-am`/`-pm`/`-eve`), what the window suits, when it is offered.
- The morning card carries a "Most booked" tag beside its icon, on the right (`pg-window-tag`). A note asks for an exact time beside Book a Visit. Header fades, windows rise (stagger 120), the note follows.
- For anyone who comes to the customer: home services, deliveries, removals, a mobile repair van. The drawing's three spans are fixed classes: an operator who moves a window's times picks the class nearest to it or deletes the drawing.

## Why do the windows' rules avoid `>`?
- The canvas wraps every node in an `.sd-wrap` ([[bootstrap-direct-child-canvas]]), so a child or sibling selector misses there: the bar is set in by a margin, not positioned, and the tag sits in the card's top row, not on its edge.
- `booking-week`'s rules still use `>`: on the canvas its days lose their grid and cards (the page is right). The gym template ships the same markup, so a fix changes both.

## What do its texts follow?
- The windows match the Home Services template's (08:00–12:00, 12:00–16:00, 16:00–19:00). The shared "Booked most often" is a plural heading in Turkish, so the tag is "Most booked" ("En çok tercih edilen"); "Times" reads "Kez", so the times' layer is "Time Range".

## How is it tested?
- `tests/designer_blocks_booking_test.php` runs the file under Node: no own group, one `h2`, a heading per day and per window, no fixed colour class, `{var}` filled, no year, the entrances, fifteen classes, three windows each drawn; the `<time>`s, the named region and the hidden drawings from the file; the four rules through `_pg_designer_block_css_parse()`, each with a theme variable, the windows' with no child selector.

Related: docs/memory/designer/designer-blocks-homeservices.md, docs/memory/designer/designer-blocks-overview.md
