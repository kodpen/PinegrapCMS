---
name: designer-sketch-keys
description: Plan (architect 2026-11-26): the Sketch tool's fix step SX1 after its sheet: a box by the keyboard, a size by the keyboard, the line in an empty sketch page, the links panel's sentence
priority: 8
---

## What is asked?
- The Sketch tool was built before its sheet; `plans/designer-drawings.md` makes what its sheet (DD4) differs in one fix step.
- The UR3 worker (#11884) found that no box can be made by the keyboard alone, and that the links panel says "Select a page to see its links." while a sketch page is selected.

## What is drawn?
- `designer/designer-sketch-tool-drawing.md`: the sheet `tool.html`, boards T1 to T6; what the step adds is marked "new" on T1, T4, T5 and T6. Where this file and the sheet differ, the sheet wins.

## What must it never do?
- Change what is not marked "new": the lining up, the chip and its kinds, the heading's keys, touch, the sketch's undo (`designer/prototype-sketch-tool.md`, `designer/sketch-feel.md`).
- Take a key from the map: `+`, `-`, `=`, `_`, `0`, Space and Enter stay its keys; a letter on a selected box still starts its heading.
- Add a table, a column or a stored value.

## Which step?
- SX1 (S) after UR3 has landed: the line "Drag to draw a box, or press B" in an empty sketch page; B on a selected sketch page adds a box (as wide as the page, 96 units high, 16 under the lowest one), also as "Add a box" in the page's "More" menu and the command palette; Alt and an arrow sizes a selected box by 8 units, Shift and Alt by 32; the links panel's sentence "A sketch page has no links until it is built.".
- The same PR: the new keys in the shortcuts help; tests in `tests/designer_sketch_test.php` for the added box's place and the size's limits; `designer/prototype-sketch-tool.md` corrected, this file deleted.

## Which files?
- `assets/js/style_designer.src.js` (the "Sketch" section), `assets/css/style_designer.css`. It waits for UR3 (#11884), which changes the same section.

Related: docs/memory/designer/designer-sketch-tool-drawing.md, docs/memory/designer/prototype-sketch-tool.md
