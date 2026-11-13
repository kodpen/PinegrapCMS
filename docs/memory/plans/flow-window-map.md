---
name: flow-window-map
description: Flow window plan, FW0 part 1: each control of today's bar and palette with its place in the drawing (the bar done in FW1); the decisions taken for the left panel
---

## Where did each control of the bar go (FW1, done)?
- "New flow" → the flow list's bar only. The page's back arrow → left in the breadcrumb strip, the one way back of every screen (`ui/back-arrow-no-copies.md`), where the drawing had it in the bar: open.
- Name, status, "Changes not published yet", save state, Retry → the left of the bar; the reader's "only its owner changes it" → the save state's place.
- Outline view → the List choice of the Canvas / List switch in the middle. Data threads → an icon toggle beside Redo, Canvas only.
- Undo, Redo, Problems, Flow runs, Run, Publish, "Only the owner publishes…" → the right, in the drawing's order; Publish filled, Run quiet (filled where there is no Publish).
- Pause, Resume → ⋯, above Share, Save as template, Delete.
- The lasting rules: `workspace/workspace-flows-window-bar.md`.

## What stays between the bar and the canvas?
- The 409 alert ("Load it again"), the run bar (the run shown, Close the run), the AI marks strip and the AI sketch box: full width over the three areas, as today; they speak of the flow, not of a box.
- The problems strip (`#wsf-problems`) stays until FW4 lists the problems in the right panel with "Go to the box"; then the Problems button opens that panel (nothing selected) and the strip goes.

## Where does each control of the palette go (FW2)?
- Head "Add a box" with a fold button (the rail of `flow-window-paths.md`).
- "Start from a template" (an empty flow only) → the first row under the head, as today: an empty flow starts there.
- Search → the search field; it unfolds the groups that hold a match.
- Section 1 Start: without a start box, the start kinds as rows; with one, a single row naming it with "Change". The line "This flow: …" and "remove that box first" go: the row says it.
- "Change" (a proposal, open: `flow-window-paths.md`): it unfolds the start kinds under the row; choosing one swaps the start box in place, its place and its arrows kept, its settings those of the new kind; one undo step; a start with settings asks first.
- Section 2 Steps: the groups Message, People, Logic, Records and tables, System, each folding, a folded one showing its count. "Records and tables" takes `row_add`, `row_set`, `row_get`, `rows_get`, `record_get` and `change` out of System (six, as drawn); a kind's colour group (`KINDS[].group`) stays, only the palette's grouping changes.
- Section 3 End; Values (constants, derived value) last, folded at first.
- A box: a 32 px row, tile and name; its long description → the row's tooltip and description (`aria-describedby`); click adds it, drag drops it, as today. The sections' hints → their headings' tooltips.
- A start kind refused while a start exists is not listed (the row says which start), not shown disabled.
- The foot line: "Click or drag onto the canvas".

## Which is the palette's look?
- Look A as drawn: 264 px, the rows' tiles in the kinds' tones (`workspace/workspace-flows-box-look.md`), no new box colour.
