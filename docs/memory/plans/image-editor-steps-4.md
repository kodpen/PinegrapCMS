---
name: image-editor-steps-4
description: Steps E26-E29 of plans/image-editor.md (owner 2026-11-03): free deformation, area selection, select subject and remove background with an own algorithm, access; claim as [plan:image-editor] E<n>
---

## Which steps are there?
- Order and decisions as in `image-editor-steps-3.md`. E27 and E28 change the save path: one at a time with the other save-path steps (`image-editor-workers.md`).

- E26 (M) Free deformation, a mode of the Vektör item (`image-editor-steps-5.md` E23b). `free`. Done in #7001 (`ui/image-editor-puppet.md`).
  - Pins on a path layer (or outlined text); dragging one bends the shape around the others (moving least squares, rigid). Kept as `puppet` {pins at rest and moved} and drawn on the flattened outline; Expand bakes it.
  - Tests: one pin moves the whole shape, two pins keep their distance at rest, a symmetric pull gives a symmetric shape.

- E27 (M) Area selection, a mode of the Fotoğraf item (`image-editor-steps-5.md` E23b). Done: cut in two, E27a (#6611) and E27b (#6678); Select area sits in the Fotoğraf group since #6819 (`ui/image-editor-rail.md`).
- E27a (M) The tool and the selection. Done (#6611, `ui/image-editor-area.md`).
  - A Select area tool on the picture: rectangle, ellipse, lasso, polygon, magic wand (tolerance, contiguous); Shift adds, Alt subtracts; invert, feather, marching ants. Kept in the document as `selection` (shapes, wand seed points and tolerance), so it opens again. Redact (E10 on its shape: a redaction area of the selection's outline) needs no server code and came with it.
  - Tests: each shape, the wand's flood fill and the outline traced from its pixels, in the model.
- E27b (M) What the selection does to pixels. Done (#6678, `ui/image-editor-area-pixels.md`).
  - Erase (see-through: a PNG, WebP or AVIF keeps it; a JPEG saves as a PNG copy unless a fill colour is chosen), Keep only (crop to it), Copy to a layer (a cut-out picture layer), Adjust inside (the Adjust panel scoped to it).
  - The server draws the mask: a step of `includes/image_engine.php` (GD and Imagick) from the selection (`M.selectionRings()`, feathered), as the redaction masks are. It changes the save path: one at a time with the other save-path steps.
  - Tests: the engine step under GD and Imagick (alpha out, the JPEG fill).

- E28 (M) Select subject and remove background. Done in three steps: E28a #6841 (`ui/image-editor-subject.md`), E28b #6889 (`ui/image-editor-subject-brushes.md`), E28c #6948 (`ui/image-editor-remove-background.md`).
  - Own algorithm, no model (owner 2026-11-03): GrabCut in a worker on the picture scaled to 800 px. The start box from an edge and saliency map, or one the person draws; colour models of 5 Gaussians each for subject and background; an own max-flow min-cut (Boykov-Kolmogorov); five rounds. Keep and Remove brushes refine it; the edge is feathered at full size by a guided filter. The result is an E27 selection, so Erase, Copy to a layer and Redact work on it.
  - Remove background: select subject, then erase the rest; a colour, a blur of the picture or another picture from Files may fill it.
  - A downloadable model may come later as an option (owner); not in this step.
  - Tests: max-flow on small graphs; GrabCut on synthetic pictures (a disc on a plain and on a noisy background) reaching an overlap (IoU) over 0.9.

- E29 (S) Access for E21-E28. The holder. Done (#7622, `ui/image-editor-access.md`).
  - Tool letters, names, the help sheet, touch sizes on a phone for the new handles and pins.
