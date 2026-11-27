---
name: designer-options-shape
description: Clip shape and Mask under Shadow and effect's "More" as built (DO14): the rows, what they write, states, the canvas preview and dashed outline, the part, the cost
---

## Where are the rows?
- Style tab, "Shadow and effect", under the section's "More" after Pointer: sub-head "Shape", "Clip shape", then "Mask", as drawn (`designer-options-layers-drawing-shape.md`). Each list has a small sign of the chosen shape beside it.
- The core leaves an empty `[data-sd-shape-box]` at the end of `_sdTfRowsHtml()`'s "More"; part `shape` draws into it (`_sdShapeBind()`). "More" opens by itself while the User Styles hold `clip-path` or a mask for the element's class (`_sdShapeHas()`, a regex look).

## What does each offer?
- Clip shape: None, Circle ("Size", "Center"), Ellipse ("Width", "Height", "Center"), Rounded inset ("Inset", "Corner"), Slant ("Edge" Top, Bottom, Both; "Amount"; "Direction" Up to the right, Up to the left), Arrow ("Points" Right, Left, Down, Up; "Point"). Center is the nine places of Origin.
- Mask: None, Fade ("Edge", "Length"), Picture ("Picture" with the folder button, then "Size" Contain, Cover, Original and "Position").
- A hand-written value no row reads shows "Custom" and is kept until another is chosen.
- The first shape chosen in a browser shows once: "What the shape cuts away cannot be clicked, and a shadow is cut with it." (localStorage `pgSdShapeHint`).

## What is written?
- `PGClipShape` (`pg_clip_shape.js`, pure) on the element's `.pgs-xxxxxx` rule or the state's, no `!important`: `clip-path: circle(50% at 50% 50%)`, `ellipse()`, `inset(5% round 16px)`, a `polygon()` for Slant and Arrow (read back by drawing every candidate again and comparing).
- Fade: `mask-image: linear-gradient(to bottom, #000 70%, transparent)`. Picture: `mask-image: url("…")`, `mask-size`, `mask-position`, `mask-repeat: no-repeat`. Every mask property has its `-webkit-` twin; a new mask clears all eight first.
- Picture with no file chosen writes nothing (the part remembers the choice per element until a file is picked): "Nothing is hidden until a picture is chosen."
- No shape or mask: nothing written, the element draws as before.

## How do states work?
- Under Hover, Focus or Pressed the lists start with "As Normal" (nothing written, Normal's shown grey) and "None" (`clip-path: none` / `mask-image: none`, taking Normal's away). A state's value equal to Normal's is not written; a state rule left empty goes.
- `FAMILIES` in `pg_option_states.js` holds `clip-path` and the mask properties as two rows of "styling", so a state's count, its ring and "Only what I changed" include them.
- The changed dot is on "Clip shape" or "Mask" only (reset kind `shape`, `_sdRowReset()` calls the part's `reset`).

## What does the canvas show?
- The written rule, through User Styles. A dragged slider sets the value inline with `!important` on the canvas element and drops it when let go.
- While the selected element's own rules hold a shape or a mask (`PGClipShape.has()`), a dashed outline (`#sd-shape-outline` in the canvas body) follows its whole box; `_sdShapeOutline()` places or removes it at each panel drawing.

## How does it load, and what did it cost?
- Part `shape` (`includes/designer_parts/shape.php`, `when: always`): loaded when "More" is drawn or an element with a shape is selected.
- Built with `php tools/build_js.php`: core 3,117,299 → 3,117,921 bytes (+0.6 KB; gzip +0.2 KB); part `states` +0.2 KB; part `shape` 20.2 KB (7.5 KB gzip).

## Where is it tested?
- `tests/designer_clip_shape_test.php` (the model, Node). The part was driven once with a stub host in Chromium (rows, writes, preview, state, reset, outline); the full editor was not run.

Related: docs/memory/designer/designer-options-effects.md, docs/memory/designer/designer-options-pointer.md, docs/memory/designer/designer-options-layers-drawing-shape.md
