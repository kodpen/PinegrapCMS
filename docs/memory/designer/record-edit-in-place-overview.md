---
name: record-edit-in-place-overview
description: Start file of record edit in place: "Edit content here" in the Preview, "Edit this record's content" / "Open the record" on the canvas; why, what binds it, which file holds what
module: record_edit
---

## What is it?
- A collection item's words and pictures corrected where they are seen: the switch "Edit content here" of the "Preview as" bar frames the item's bound texts and pictures, a click edits with the field's own control, and the foot bar "2 changes to … · Save to the record · Discard" writes them, with the line "This changes the record wherever it is shown."
- On the canvas a bound element's menu and the command palette offer "Edit this record's content", which opens the Preview in that state; a product, an event or a change that waits shows "Open the record", a link to its own screen.

## Why, and what binds it?
- Owner (2026-11-24): a wrong word seen on the page is put right where it is seen; level 3 of the ten features that keep the designer simple and quick (`workflow/decisions-made-product-108.md`, `designer-light-and-simple.md`).
- Design and content stay apart: a record's save never changes the page's tree, is not part of Publish or of the design's undo, and has its own Save and Discard.
- Saved only through the item's own writer, so the field's rules, the address rename and the item's history apply (`collection-revisions.md`), with the designer named as the source. Only who may edit the item on its own screen sees the switch.
- A field changed by someone else meanwhile is never overwritten: the bar shows both and asks.
- Architect (2026-11-25, agent channel message 13357): with "Content changes wait for approval" on, a role 2 or 3 person gets no "Edit content here" switch; where the read says `waits: true` the Preview shows "Open the record" in its place and the record is changed on its own screen, which is not held either.
- Why: approval of collection records is left out of content approval and needs the owner's yes of its own (`content-proposals-overview.md`), so no record op and no approve path go into `design_proposals`.
- No table or column was added; no bar button (the row lives in the canvas menu and the command palette).

## Which file holds what?
- `record-edit-in-place-server.md` (RE1, #11551): `includes/fn/record_edit.php`, the actions `record_edit_read` / `record_edit_save`, rights, approval, the changed-meanwhile check, the write order.
- `record-edit-in-place-spots.md` (RE2, #11583): `PGRecordEditSpots`, which bound element takes which field and control.
- `record-edit-in-place-preview.md` (RE3, #11677): the part `recordedit`, the switch, frames, controls in place, the foot bar, conflicts.
- `record-edit-in-place-canvas-row.md` (RE4, #11690): the canvas and palette row, `preview_record_open`, the link for a product or an event.
- The drawing: `designer-record-edit-drawing.md` (boards R1 to R3) and `designer-record-edit-drawing-save.md` (R4 to R6); it decides where words and drawing differ (`workflow/screen-follows-drawing.md`).

## How is a new control or kind added?
- A field kind: `pg_rec_edit_control()` on the server, `accepts()` in `pg_record_edit_spots.js` for the property, the control in `style_designer_recordedit.js`; tests in `tests/record_edit_test.php`, `record_edit_spots_test.php`, `record_edit_preview_test.php`.
- Another record kind written in place needs its own writer with history and the owner's yes; until then it gets "Open the record" (`pg_rec_edit_open_screen()`).
