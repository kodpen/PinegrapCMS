---
name: image-editor-steps-3
description: Steps E21-E25 of plans/image-editor.md (owner 2026-11-03): change a placed sticker, text to outlines, scissors, shape builder, warp; claim as [plan:image-editor] E<n>
---

## Which steps are there?
- Owner 2026-11-03 (`workflow/decisions-made-product-24.md`): the small tools first (E21-E23, side by side), then E24-E28 (`image-editor-steps-4.md`); the animation steps run beside them; video last (`image-editor-video.md`). Sizes, files and review as in `image-editor-workers.md`.

- E21 (S) Change a placed sticker, icon or emoji. `free`, beside E22 and E23. Done (#6351).
  - With one sticker, Bootstrap icon or emoji layer selected, a Change button in its panel opens the Sticker grid (and the glyph part's icon and emoji sections); a pick replaces that layer's glyph instead of adding a layer. Place, size, turn, colour, opacity and motion stay; one undo step.
  - Tests: the swap keeps every field but the glyph (model); in the browser an emoji to an icon and back, undo.

- E22 (M) Text to outlines. Done (#6357).
  - "Convert to outlines" on a text layer: its letters become one path layer in the same place, size, turn, colour and effects; one undo step. A new part `outline` with an own OpenType reader: cmap, glyf/loca, CFF, hmtx, pair kerning (kern, GPOS pairs); spacing and alignment from the text tool's layout.
  - The font file: a site font by its URL; a Google font through an action `font` that fetches the family's TTF once into `data/` and serves it (browsers get WOFF2, which needs Brotli); a system font has no file: the button says so. Scripts that need shaping (Arabic, Indic) get a message for now.
  - Tests: the reader on small TTF and OTF fixtures (a contour, an advance, a kerning pair); the outline against the text layout.

- E23 (S) Scissors. Done (#6355).
  - A Scissors tool beside the pen: a click on a path cuts it there (`M.splitAt()`): an open path becomes two, a closed one opens; a drag across paths cuts each where the line crosses. A shape is converted to a path first.
  - Tests: cuts on lines, curves and closed shapes in the model (knot counts, end points).

- E24 (M) Shape builder. After E23, beside E25. Done (#6405).
  - Pathfinder buttons for two or more selected shapes or paths: unite, subtract front, intersect, exclude, divide. A Shape builder tool: drag across overlapping regions to merge them, Alt-drag to remove one.
  - Own path booleans: curves flattened (0.25 px), polygon clipping (Martinez-Rueda), refitted to curves (`M.refitPath()`), holes as even-odd subpaths. Tests: the five operations on squares and circles, holes, touching edges.

- E25 (M) Warp. After E23, beside E24. Done (#6408).
  - On a path, a shape or outlined text: presets arc, arch, bulge, flag, wave, fish, rise, squeeze, twist with bend and distortion, and a free envelope mesh (3×3 up to 6×6 handles). Kept as `warp` in the document and drawn by mapping the flattened outline; Expand bakes it into the path. A live text is offered E22's conversion first.
  - Tests: each preset maps corner and middle points as expected; Expand equals the drawn outline.
