---
name: designer-shared-marks
description: Plan (owner 2026-11-25): shared components and system widgets no longer wrapped at rest on the canvas; a frame under the pointer and when chosen, a View switch; steps SM1, SM2
priority: 8
---

## What is asked?
- Owner (2026-11-25): he had wanted shared components to stand out quickly in the designer, but the green and purple wrapping bothers him; a customer of his asked why the parts were wrapped and it took explaining (`workflow/decisions-made-product-111.md`).

## What is there today?
- The canvas styles of `style_designer.src.js` wrap every shared component in a green ring, 3 px outside and 3 px inside, on `.sd-shared-wrap` and again on each direct child, and every system widget in a 2 px indigo border (`.sd-system-wrap`), all the time. The border makes a widget 4 px wider and taller than the site draws it.
- The name tag of a part reads "[Shared] Header" or "[System] Cart"; a system widget's band shows under the pointer and when chosen.

## What is built?
- At rest nothing is drawn on a shared component or a system widget: no ring, no border, no tint, no tag. The page on the canvas has the site's sizes.
- Under the pointer: a 1.5 px frame in the part's colour (green `#10b981` shared, indigo `#6366f1` system) and a name tag in that colour, "Header · Shared", "Cart · System". Only the innermost part gets the tag; a component or widget the pointer is inside of shows a 1 px line.
- Chosen: a 2 px frame in its colour, the tag with the grip. The line of the component round a chosen element stays while it is chosen.
- A switch row "Shared parts" in the canvas bar's View menu, second under "Outline", and "Show shared parts" / "Hide shared parts" in the command palette: on, every shared component wears a soft wash, a 1 px line and its tag, every system widget a wash and a line. Off at first; kept where the other View switches are kept; counted in the menu's badge.

## What must it keep?
- A frame takes no room: no size and no neighbour's place changes, also where the wrapper has no box of its own (the frame goes on the child, as the ring does today).
- The warning at the first change inside a shared component, the dashed stand-ins (empty, loading, a loop's template), Outline, the Overview's and the palette's colours, the Options panel: untouched.
- A content editor gets the same frame and tag under the pointer, no grip.
- The switch is Design mode's canvas only: not Preview, not a picture of the page, not Prototype mode. No new button, no key (`designer/designer-light-and-simple.md`).
- Every text is translated. No table or column.

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- SM1 (S) — done in #11662. The marks at rest, under the pointer and chosen, the tag's words, the View switch and the command palette row. Boards Q1 to Q4 of `designer/designer-shared-marks-drawing.md`; where a line here differs, the drawing wins. The PR body carries a table of the boards: what was built for each, what differs and why, and names the change for the `[FIX]` line.
- SM2 (S) Close-out: `designer/shared-component-model.md` and `designer/designer-canvas-bar.md` say the new marks and the row, this file deleted.

## What does every step keep?
- Claim as `[plan:designer-shared-marks] <step> — <topic>`. #11623 (CG5) adds its own row to the View menu: the later of the two keeps both rows.
- Checked on the dev site in the PR: a page with a shared header holding a cart and a login region, at rest, under the pointer, chosen, and with the switch on.
