---
name: accessibility-view-see-as
description: The Accessibility view's "See as" sight filters (AV3, board V5): the menu, the filter on the canvas frame only, the pill, never kept
---

## Where is it?
- A row under the three kinds with a button that opens a menu: Off, Red-weak ("1 in 100"), Green-weak ("5 in 100"), Blue-weak, No colour, Blurred; arrows, Home/End, Escape. The narrow bar has it as a second select.

## How is the filter laid?
- The filter is `style.filter` on the editor's canvas `<iframe>` (`applySee()`): a feColorMatrix of an SVG the part puts in the editor's document (`#sd-av-see-defs`, sRGB, the drawing's matrices), `grayscale(1)` or `blur(1.6px)`. The page, the tree, the Preview and the saved design never have it; the marks, card and panels sit outside the frame and keep their colours.
- While on, a pill "Seen as: green-weak" with "Turn off" stands 18 px above the canvas's foot, centred (54 px over the narrow bar).

## Is it kept?
- Never kept: not in `pg_sd_a11y`; it goes with the view (`hide()` clears the frame and removes the SVG) and on reload; a new canvas document (another page) keeps it while the view stays on.

## What differs from the drawing?
- The drawing's red- and green-weak matrices are full protan/deutan ones; they are used as drawn, with `color-interpolation-filters="sRGB"`. Blue-weak is the usual tritan matrix, which the drawing does not give.

Tests: `tests/designer_a11y_view_test.php` (`_avSee*`). Card and marks: `accessibility-view.md`; drawing: `designer-accessibility-view-marks-drawing.md`.
