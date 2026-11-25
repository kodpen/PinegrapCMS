---
name: designer-canvas-guides
description: Plan (owner 2026-11-24): on the designer's canvas, distances measured with a key, spacing dragged by its bands, columns dragged over the grid, equal gaps marked; client only; steps CG1 to CG6
priority: 7
---

## What is asked?
- Owner (2026-11-24): the seven additions go in (`workflow/decisions-made-product-106.md`). This one: spacing and alignment set where the eye is, on the canvas, not read off a panel.

## What is there today?
- Spacing is typed, stepped or dragged in the Style panel's box (`designer/designer-spacing-box.md`); an element is moved in the tree or by its Move window. The canvas shows a selection frame and nothing of distance, gap or column.

## What is built?
- Measure: with an element selected, holding Alt and pointing at another shows the distances between their edges in pixels, lines with numbers; pointing at the parent shows the four inner distances.
- Spacing bands: the selection shows its outer spacing as a tinted band outside its frame and its inner spacing inside. A band's edge is a handle: dragging steps that side through the spacing scale, the value beside the pointer; Shift takes the opposite side with it, Alt all four, as in the Spacing box.
- Equal gaps: while a handle moves, a sibling gap of the same size is marked with the same number, and the handle holds there a moment.
- Columns: in a row, a column's side edge is a handle; dragging steps it over the twelve columns with the grid drawn over the row and "6 of 12" beside the pointer, for the screen size the canvas bar shows.
- Layout lines: a key draws the page's container, its columns and gutters over the whole canvas until pressed again.
- "Canvas guides" in the canvas bar's View menu (`designer/designer-canvas-bar.md`) turns all of it off or on, kept per browser; each key is shown beside its row there.

## What must it keep?
- A handle writes what the Spacing box and the column control write (the same classes, or a variable once `designer-variables.md` DV3 has landed), for the chosen screen size, one undo step per drag.
- No handle on a node the operator may not edit: a content editor's closed parts, a shared component's inside, a system widget's kept parts.
- Nothing enters the page's tree or its saved markup; the lines live in the canvas's own layer and never show in the Preview.
- Keyboard too: a handle takes focus, arrows step it, Escape puts the value back. No listener while nothing is selected.
- A designer part of its own, loaded on first use as the Spacing box's part is (`pg_designer_parts()`); the designer's main source only calls it.

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- CG1 (M) done (#11517). The geometry, pure: distances between two boxes, a parent's inner distances, the scale's nearest step, the column under a pointer, equal gaps among siblings; tested as the designer's other pure scripts are.
- CG2 (M) after CG1 and DD8: Measure.
- CG3 (M) after CG1 and DD8: the spacing bands and handles.
- CG4 (M) after CG3: the column handles, the grid over the row, equal gaps.
- CG5 (S) after CG2: layout lines, the View menu's switch, the keys shown.
- CG6 (S) Close-out: memory in `designer/`, this file deleted, the `[NEW]` line named.

## What does every step keep?
- No table or column. The drawing: `designer-drawings.md`. Claim as `[plan:designer-canvas-guides] <step> — <topic>`.
