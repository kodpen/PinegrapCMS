---
name: designer-screens-side-by-side
description: Plan (owner 2026-11-24, level 1): the designer's canvas with a phone and a tablet mirror beside it, redrawn when the hand rests, a click choosing the element; steps SS1 to SS4
priority: 7
---

## What is asked?
- Owner (2026-11-24): ten more features, in levels, the designer kept simple and quick (the decision part 108; `designer/designer-light-and-simple.md`). This one: a change seen on every screen at once.

## What is there today?
- The canvas bar's six screen sizes show one width at a time (`designer/designer-canvas-bar.md`); to see a phone the designer leaves the desktop view.

## What is built?
- "Side by side" as a switch of the canvas bar's View menu. On: the canvas area holds the canvas being edited and two mirrors, a phone (375) and a tablet (768); each mirror's head names its width and has the list of sizes.
- A mirror is a picture of the same draft at its width, scaled to fit the height: the canvas's own markup and styles in a frame with no editor script. It is redrawn when the hand rests, never during a drag or while a text is typed.
- The chosen element is outlined in every mirror and brought into view there. A click on an element in a mirror chooses it on the canvas.
- "Edit at this size" in a mirror's head swaps: the canvas takes that width and the mirror takes the canvas's. "Fit" in the bar fits all three.
- An e-mail design shows its two widths. A window too narrow for three shows the row dimmed with the reason.
- The switch and the mirrors' sizes are kept per browser.

## What must it keep?
- Editing happens in one place only, the canvas; a mirror takes no typing and no drag.
- Off: the frames leave the page and nothing of the feature runs. On, on the fixture page, typing and dragging on the canvas feel as before (measured as the rule file says).
- A mirror draws what the canvas draws for its width: the same samples, the same colour mode, no visitor script, no call to the site.

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- SS1 (M) done (#11535) The mirror as a part: a frame filled from the canvas's output, the redraw when the hand rests, its scheduling pure and tested.
- SS2 (M) after SS1 and DD12: `'mirror'` added to the parts list of `includes/designer_screen.php`; the layout of three, the heads with their size lists, Fit, the narrow window.
- SS3 (S) after SS2: the chosen element followed, a click choosing, "Edit at this size".
- SS4 (S) Close-out: memory in `designer/` with the numbers measured, this file deleted, the `[NEW]` line named.

## What does every step keep?
- No table or column. The drawing: `designer-drawings-levels.md`. Claim as `[plan:designer-screens-side-by-side] <step> — <topic>`.
