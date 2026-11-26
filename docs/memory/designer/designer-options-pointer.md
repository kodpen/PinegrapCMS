---
name: designer-options-pointer
description: Cursor and "Let clicks through" under Shadow and effect's "More" as built (DO17): the rows, what they write, Normal only, the canvas, the part, the cost
---

## What do the Pointer rows hold?
- In "Shadow and effect", under the section's "More" after Blend (`_sdPtRowsHtml()`): sub-head "Pointer", then "Cursor" (Auto, Pointer, Default, Not allowed) and "Let clicks through" (a switch).
- "More" opens by itself while either is set. Shown for one element whose custom style is on (`_sdStateNode()`), as Transform; under Hover, Focus or Pressed the two rows are not drawn (Normal only: a cursor shows while the pointer is over the element, so a state adds nothing).
- Once "Let clicks through" is on, one info line says clicks go to what is behind, on the canvas too, and to pick the element in the Tree.

## Where is it written?
- Cursor: `PGOptionStates.cursorWrite(text, cls, v)` (part `states`) puts `cursor: pointer|default|not-allowed` on the element's `.pgs-xxxxxx` rule in User Styles, no `!important`; Auto takes it out. `cursorRead()` reads Normal's rule only; a cursor no row writes (`zoom-in`, `grab`) reads as `'own'` ("Your own") and stays until a row's value replaces it; the reset arrow takes it out.
- "Let clicks through" is Bootstrap's `pe-none` class on the element, through the panel's class switch (`data-util-toggle`), so undo, the Attributes chips and the class list follow as for every class switch.

## What does the canvas show?
- The canvas honours `pe-none` as the site does: a click passes to the element behind it. No canvas rule makes it clickable again, so an overlay set to let clicks through does not cover what is under it while editing.

## How does it load, and what did it cost?
- Rows are core markup; the cursor model is in part `states`, loaded when the User Styles hold a `cursor` with the element's class (`_sdPtOf()`, a regex look, no parse) or the row is first used. The switch needs no part.
- terser 5.51.2 `-c -m`: core 2,660,000 → 2,662,119 bytes (+2.1 KB; gzip +0.5 KB); part `states` 21,515 → 22,038 bytes (+0.5 KB; gzip +0.15 KB). Node edit-cycle timing within noise (undo snapshot 0.84 → 0.83 ms, baseline 1.27 → 1.24 ms).

## What is not built, and where is it tested?
- The Options search finds the rows only while "More" is open, as for Skew and Origin. No browser run: the rows draw nothing while the pointer moves.
- `tests/designer_option_states_test.php` (`test_designer_option_states_cursor`). The panel is not unit tested.

Related: docs/memory/designer/designer-options-effects.md, docs/memory/designer/options-panel-census-look.md
