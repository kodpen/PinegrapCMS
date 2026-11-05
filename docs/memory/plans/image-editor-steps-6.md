---
name: image-editor-steps-6
description: Steps E28a-E28c of plans/image-editor.md: select subject (own GrabCut in a worker), Keep and Remove brushes with the soft edge, remove background; claim as [plan:image-editor] E<n>
---

## Which steps are there?
- E28 of `image-editor-steps-4.md` (owner 2026-11-03: own algorithm, no model) cut in three. Sizes, files and review as in `image-editor-workers.md`.

- E28a (M) Select subject. Done (#6841, `ui/image-editor-subject.md`).
  - The worker `pg_image_editor_subject.js` (also on the page where a site's policy refuses the worker, and under Node for the tests): own Boykov-Kolmogorov max-flow, colour models of 5 Gaussians, GrabCut in five rounds on the picture at most 800 px a side; the start box from an edge and saliency map, or the rectangle the person drew. "Select subject" in the Select area panel; the result is a selection op `subject` with its rings, so Erase, Keep only, Copy to a layer, Adjust inside and Redact work on it. No server code beyond the worker's address.
  - Tests: max-flow on small graphs; GrabCut on a disc on a plain and on a noisy background, IoU over 0.9; the start box; the op in `selectionRings()`.

- E28b (M) Keep and Remove brushes and the soft edge. Done (#6889, `ui/image-editor-subject.md`).
  - Strokes kept on the `subject` op as hard labels, GrabCut run again from them; the edge feathered at full size by a guided filter.

- E28c (M) Remove background. After E28b. Changes the save path: one at a time with the other save-path steps.
  - Select subject, then erase the rest; a colour, a blur of the picture or another picture from Files may fill it (the engine's mask step gets the two new fills).
