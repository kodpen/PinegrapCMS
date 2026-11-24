---
name: workspace-flows-thread-focus
description: Flows canvas data threads drawn only for the box in hand (FE4): threadFocus(), the N values mark, pointing, the strip's Data threads drawing all, run view and read-only card
---

## Which threads are drawn? (FE4, boards 1a and 1b)
- At rest none and no row. `drawData()` draws only the threads touching `threadFocus()`: the box a thread is being drawn from, the chosen box (a chosen thread: the box it goes into) and the pointed box (`state.pointed`). Nothing is dimmed (`.wsf-dim` is gone).
- The box in hand shows its chips, the dots its threads leave on other boxes, and in a draft its own unused values as dashed rings at the row's end.
- `markThreads()` puts on each other reading box one mark (`.wsf-data-mark`, 18 px): a 6 px dot per value in its tone (red for a missing one), then "1 value" / "N values" (`fl_values_one`, `fl_values_mark`); `aria-hidden`.
- `pointThreads()` (pointerover/pointerout on `ui.boxes`, bound in `bindCanvas()`): a pointed box shows its threads at once; they go 200 ms after the pointer leaves the boxes; not while a thread is being drawn.
- A dot's name has the ground's colour behind it.
- The strip's "Data threads" (`threadsButton()`) pressed draws every thread (`state.threads`); it starts not pressed, the choice kept in localStorage `pg_wsf_threads` (`all` / `focus`; an older `on` / `off` reads as not pressed). The run view and the read-only card (`state.threads = false` in the embed) follow the same rule.
- Tests `…_data_row_drawing`, `…_thread_bends_around_a_box`; harness scenario "data threads" in `tests/js/flows_canvas_scenarios.js`.

## Why?
- Before, every thread was drawn all the time along the dots' rows, and a chosen box dimmed the others to 25%; the canvas read as a web of lines (`workflow/decisions-made-workspace-47.md`).

Related: workspace-flows-thread-drawing.md
