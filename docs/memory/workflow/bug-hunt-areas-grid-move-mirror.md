---
name: bug-hunt-areas-grid-move-mirror
description: The bug hunt of the designer's grid, move and mirror parts (#12021): the seven faults it fixed, the one it opened as an issue, what it read clean
---

## What was read?
- `assets/js/style_designer_grid.js`, `style_designer_move.js`, `style_designer_mirror.js` and their pure modules `pg_grid_areas.js`, `pg_grid_tracks.js`, `pg_mirror_schedule.js`; the core's `_sdMoveCheck()`, `_sdMoveNodes()`, `_sdMoveHost()` and `_sdSzHost()` read only.
- Asked to look at: tracks and areas after a column is removed, a move into a closed block or a shared component, the mirror in right-to-left languages, undo.

## What did it fix?
- An area named as a CSS keyword ("inherit", "AUTO", "Span") made a child's `grid-area` that keyword: `PGGridAreas.nameError()` and `item()` refuse them in any case.
- The areas tint over a right-to-left grid stood over the mirrored cells: `PGGridAreas.box()` counts columns from the right edge there.
- The areas tint was copied into the Side by side mirrors and its redraws drew them again: it carries `.sd-ga-overlay`, one of the mirror's chrome classes.
- A Columns or Rows change on a grid whose other axis was Default cut the areas by zero tracks and removed every one, with the children's "Area": the cut runs only while both axes have a count.
- The line under the areas ("Columns went from 3 to 2…") stayed after Undo took the change back: it is drawn only while the box's areas and tracks are as the change left them.
- "Move…": after a target was chosen, a new search and Enter moved to the earlier target, not the lit row; the list now starts on the target's row and Enter picks a lit row that is not the target.
- View › Shared parts tinted the mirrors too: `sd-parts-lit` is one of the mirror's state classes.

## What became an issue?
- #12022: `_sdMoveCheck()` asks the rights of the element moved next to, not of its parent, so a content editor can move an element Before or After an editable area's own top element, out of the area; Save drops it. In `style_designer.src.js`, left alone by the hunt.

## What read clean?
- A move into a shared component (its own tree, nesting refused by `canDrop()`), a move into a locked block (refused with its words), the moved elements and their insides left out of the targets.
- Areas from a smaller size, their copy on the first change, remove and rename walking the children by size, Reset this size.
- The mirror's schedule, its widths and swap, the chosen element's frame and its follow in a right-to-left page.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/designer/designer-grid-areas.md, docs/memory/designer/designer-canvas-mirror.md, docs/memory/designer/overview-move-without-drag.md
