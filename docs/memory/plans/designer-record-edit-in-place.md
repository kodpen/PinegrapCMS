---
name: designer-record-edit-in-place
description: Plan (owner 2026-11-24, level 3): a collection item's words and pictures corrected in place in the designer's Preview, saved to the record by its own writer, apart from the design; steps RE1 to RE5
priority: 5
---

## What is asked?
- Owner (2026-11-24): ten more features, in levels, the designer kept simple and quick (the decision part 108; `designer/designer-light-and-simple.md`). This one: a wrong word seen on the page is put right where it is seen.

## What is there today?
- The canvas draws samples for bound content (`designer/canvas-sample-overview.md`); the Preview draws a record, and with the preview-as plan a chosen one. A record's word is corrected on the collection's own screen.

## What is built?
- "Edit content here" as a switch of the "Preview as" bar, shown when a collection item is chosen. On: the item's texts and pictures on the page get a dashed frame in the content colour, each with the field's name on hover.
- A click edits in place with the field's own control: a line of text, the small text bar for formatted text, a number, a date, the picture chooser.
- A bar at the foot while something is changed: "2 changes to Blue Mountain Coffee · Save to the record · Discard". Under it one line: "This changes the record wherever it is shown."
- On the canvas, a bound element's menu gets "Edit this record's content", which opens the Preview in that state; a product, an event or another kind of record shows "Open the record" in its place, a link to its own screen.

## What must it keep?
- Design and content stay apart: a record's save never changes the page's tree, is not part of Publish or of the design's undo, and has its own Save and Discard.
- Saved only through the item's own writer, so the field's rules, the address rename and the item's history apply (`designer/collection-revisions.md`), with the designer named as the source.
- Only who may edit that item on its own screen sees the switch.
- Architect (2026-11-25, agent channel message 13357): with "Content changes wait for approval" on, a role 2 or 3 person gets no "Edit content here" switch. Where the read says `waits: true` the Preview shows "Open the record" in its place (RE4's link), and the record is changed on its own screen as today, which is not held either.
- Why: approval of collection records is left out of content approval and needs the owner's yes of its own (`designer/content-proposals-overview.md`, "What is left out?"), so no record op and no approve path go into `design_proposals`.
- A field changed by someone else meanwhile is not overwritten: the bar shows both and asks.

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- RE1 (M) Server: one item's fields written through its own writer. Done (#11551): `designer/record-edit-in-place-server.md`. A role 2/3 change while approval is on is refused (`waits`), not held.
- RE2 (S) Pure: which bound elements of a page can be edited and with which control. Done (#11583): `designer/record-edit-in-place-spots.md`; RE3 bundles `pg_record_edit_spots.js` into its part.
- RE3 (M) boards R1 to R5 and the narrow window of R6. Done (#11677): `designer/record-edit-in-place-preview.md`; part `recordedit`, `waits: true` shows "Open the record".
- RE4 (S) after RE3: "Open the record" for other kinds (board R1), the row of the canvas menu and of the command palette (board R6). The PR body carries the same table. Done (#11690): `designer/record-edit-in-place-canvas-row.md`.
- RE5 (S) Close-out: memory in `designer/`, this file deleted, the `[NEW]` line named.

## What does every step keep?
- No table or column; a step that needs one stops and asks the architect. The drawing: `designer/designer-record-edit-drawing.md` and `designer-record-edit-drawing-save.md`; it decides where the two differ (`workflow/screen-follows-drawing.md`).
- Claim as `[plan:designer-record-edit-in-place] <step> — <topic>`.
