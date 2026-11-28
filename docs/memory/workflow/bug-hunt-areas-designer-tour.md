---
name: bug-hunt-areas-designer-tour
description: The bug hunt of the designer's first tour (2026-11-28, area of bug-hunt-areas-7.md): the four faults fixed in style_designer_tour.js, what was read clean
---

## What was read?
- `assets/js/style_designer_tour.js` (the part `tour`), `assets/js/pg_designer_tour.js` (the model), `includes/fn/tour.php` (the panel's guided tours), with `tests/designer_tour_part_test.php` and `tests/designer_tour_model_test.php`.
- The core's calls into the part (`openShortcutsModal()`, `_sdTourDue()`, `_sdTipAsk()`, `_sdEmptySync()`) were read, not changed.

## Which faults were fixed?
- A "How do I…" task or "Take the tour" chosen in the Help window got no focus and was not read out: it starts while the dialog is still closing (Bootstrap 6 keeps it `[open]` with `.hiding`), the bubble was hidden, and the end gave the focus back into the closed dialog. Now the stop is presented when the bubble shows, and the end goes back to where the dialog put the focus.
- A redraw of the count (another stop's control came or went) took the focused button away: the focus fell to the page and the arrows left the tour. Now it stays on the same button, else Next or Done.
- Escape that ended the tour or put the card away went on to the editor: the same press cleared the selection or closed the Prototype view. Now it stops there; Escape for an open menu, dialog or typed field goes on.
- The first card said "Five stops" under 576 px, where the tour shows four (no palette): it now says "Four stops on the real buttons.".

## What was read clean?
- The model: stops and tasks per access, `move()` and `first()`, `visible()`, `escapes()`, `read()`/`write()` of the browser state, tips and starts.
- The card's wait for a quiet moment and the state written before it shows; the tip line; the empty page's starts; the Help rows and the "Tips" switch.
- `includes/fn/tour.php`: the list of seen keys, the allowed keys, an older version dropped, the token handed under `saveToken`.

## What was seen and left?
- A tip line left in a panel moved out of the window still counts as shown, so another feature's tip waits for the next visit. Small and needs a rule on "shown"; no issue opened.
- The press watch of a task and the empty page's drag watch listen to the canvas document of the moment they start; a canvas reloaded meanwhile is not heard. No task's pointer is known to reload the canvas.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workflow/bug-hunt-areas-7.md, docs/memory/designer/designer-first-tour-card.md
