---
name: designer-record-edit-in-place
description: Plan (owner 2026-11-24, level 3): a collection item's words and pictures corrected in place in the designer's Preview, saved to the record by its own writer, apart from the design; steps RE1 to RE5
priority: 5
---

## What is asked?
- Owner (2026-11-24): ten more features, in levels, the designer kept simple and quick (the decision part 108; `designer/designer-light-and-simple.md`). This one: a wrong word seen on the page is put right where it is seen.

## What is there today?
- The canvas draws samples for bound content (`designer/canvas-sample-overview.md`); the Preview draws a record, and with the preview-as plan a chosen one. To correct a record's word the designer goes to the collection's own screen and comes back.

## What is built?
- "Edit content here" as a switch of the "Preview as" bar, shown when a collection item is chosen. On: the item's texts and pictures on the page get a dashed frame in the content colour, each with the field's name on hover.
- A click edits in place with the field's own control: a line of text, the small text bar for formatted text, a number, a date, the picture chooser.
- A bar at the foot while something is changed: "2 changes to Blue Mountain Coffee · Save to the record · Discard". Under it one line: "This changes the record wherever it is shown."
- On the canvas, a bound element's menu gets "Edit this record's content", which opens the Preview in that state.
- A product, an event or another kind of record shows "Open the record" in its place, a link to its own screen.

## What must it keep?
- Design and content stay apart: a record's save never changes the page's tree, is not part of Publish or of the design's undo, and has its own Save and Discard.
- Saved only through the item's own writer, so the field's rules, the address rename and the item's history apply (`designer/collection-revisions.md`), with the designer named as the source.
- Only who may edit that item on its own screen sees the switch.
- Architect (2026-11-25, agent channel message 13357): with "Content changes wait for approval" on, a role 2 or 3 person gets no "Edit content here" switch. Where the read says `waits: true` the Preview shows "Open the record" in its place (RE4's link), and the record is changed on its own screen as today, which is not held either.
- Why: approval of collection records is left out of content approval and needs the owner's yes of its own (`designer/content-proposals-overview.md`, "What is left out?"), so no record op and no approve path go into `design_proposals`. RE1 stays as built.
- A field changed by someone else meanwhile is not overwritten: the bar shows both and asks.

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- RE1 (M) Server: one item's fields written through its own writer with rights, rules, history and the changed-meanwhile check; tests. Done (#11551): `designer/record-edit-in-place-server.md`. A role 2/3 change while approval is on is refused (`waits`), not held, and stays so (the architect's line above).
- RE2 (S) Pure: which bound elements of a page can be edited and with which control.
- RE3 (M) after RE1, RE2, PW3 of the preview-as plan and DD20: the switch, the frames, the controls in place, the foot bar. With `waits: true`: no new control, only RE4's "Open the record" link where the switch would be.
- RE4 (S) after RE3: "Open the record" for other kinds, the canvas menu's row.
- RE5 (S) Close-out: memory in `designer/`, this file deleted, the `[NEW]` line named.

## What does every step keep?
- No table or column; a step that needs one stops and asks the architect. The drawing: `designer-drawings-levels.md`.
- Claim as `[plan:designer-record-edit-in-place] <step> — <topic>`.
