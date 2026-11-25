---
name: design-variables-spacing
description: The spacing box's sides and the corners offering a design's variables (DV3b): how a side is bound, what it writes, the chip rows under the box, the classes it drops
---

## Where does a side offer variables?
- Style › Spacing (the box) and Style › Border and corner (the corners' centre, "Radius"), part `spacing`, on one element of a design with themes and Bootstrap (`_sdSpBoxHost()` `vars`). Several elements or no themes: the box as before.
- No new button stays in the panel: clicking a side opens its typed field as today, and a quiet "Variables" button (braces) sits beside that field. It opens the list of the part `variables` (`design-variables-options.md`).
- The four corner sides take no variable: only the centre, which rounds all four.

## What does the list offer?
- A side of the box (kind `space`): "Base unit", "Section rhythm", "Your own" sizes, then "Units" (2, 3, 4, 6, 8 times the base unit, each with its px now). The corners (kind `radius`): "Corners" and "Your own" sizes. Then "Custom value…", which drops the binding and opens the side's typed field.

## What does a bound side write?
- An inline style on the element: `padding-top`, `padding-inline-end`, `margin-bottom`, `margin-inline-start`… (logical, as Bootstrap's `pe-*`/`ms-*` are; `PGSpacingBox.styleProp()`), or `border-radius` for the corners.
- In the same undo step the side's classes go at every screen size (`PGSpacingBox.clearSide()`; the corners lose every radius class): Bootstrap's spacing and radius utilities are `!important` and would beat the inline value.
- Writing a class on a side (typing, dragging, arrow keys) drops that side's inline value in the same step; any corner write drops `border-radius`.

## How is a bound side shown?
- In the box: braces in the side's place; a click opens the list, Delete removes the binding. A typed inline value (after "Detach") shows as written and is replaced by the next class written there.
- Under the box: a row per bound side, its name ("Padding Top", "Corners") and the chip of DV3 (`_sdVarChipHtml()`) with "Detach".

## What was measured?
- PR #11622 (Node 22, gzip -9): core `style_designer.min.js` 3,053,688 → 3,054,301 bytes (gzip 799,553 → 799,782); part `spacing` 31,612 → 36,408 (gzip 10,908 → 12,339); part `variables` 14,906 → 15,468 (gzip 5,049 → 5,220). The parts load on first use, so opening the designer pays only the core's 613 bytes. No browser run.

Test: `tests/designer_spacing_box_test.php` (`styleProp`, `clearSide`, the host and kinds).
