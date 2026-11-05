---
name: image-editor-subject
description: The image editor's Select subject (E28a, #6841): own GrabCut and Boykov-Kolmogorov max-flow in a worker, the start box, the subject op with its rings in doc.selection
module: image_editor
---

## Where does it live?
- A "Select subject" button at the top of the Select area panel (`areaSubject()` in `pg_image_editor_area.js`). The cut is `assets/js/pg_image_editor_subject.js`, its own bundle `pg_image_editor_subject.min.js`, address `config.subject` (`pg_image_editor_config()`); it runs as a worker, on the page (`window.PgImageSubject`) where a site's policy refuses the worker, and under Node for `tests/image_editor_subject_test.php`. Labels `subject*` in `pg_image_editor_strings_area()`.
- No trained model (owner 2026-11-03): GrabCut on the picture read back at most 800 px a side (`areaPixelsAt()`), five rounds; colour models of 5 full-covariance Gaussians (k-means start); eight neighbours, gamma 50, beta from the mean contrast; pixels outside the box are background.

## Where does the cut start?
- A selection that is one rectangle (added, not inverted) is the box, and the subject replaces it. Else `startBox()`: each pixel's (3 x 3 smoothed) colour distance from the nearest of five border colours (k-means over a 4 % band) with a quarter of its edge strength; pixels over the mean plus half a deviation; the box from their 2nd to 98th percentile each way, grown by 4 %. A flat picture has none ("No subject was found"). A box keeps a pixel of background round it (`clampBox()`).

## How is the result kept?
- An op `{ op, kind: 'subject', rings, box }` in `doc.selection` through `areaAdd()` (New, Add or Subtract as the panel says), one undo step. Unlike a wand the rings are kept, since the cut takes about a second at 800 px: `M.subjectRings()` traces the mask (`maskRings()`), drops specks and holes under 0.05 % of the picture, smooths the steps within 0.75 px (`M.simplifyRing()`), carries them to the turned space. `selectionRings()` takes an op's `rings` before its `ring`, so Erase, Keep only, Copy to a layer, Adjust inside and Redact work on it.

## What does the max-flow do?
- `Graph` is Boykov-Kolmogorov: two search trees grown from the terminals, augment, adopt orphans by the shortest valid path (time stamps and distances). Terminal links are kept as one signed residual (`terminal()`). Checked against Edmonds-Karp on 40 random graphs, the cut costing the flow.

## How was it checked?
- Node: max-flow, GrabCut on a disc on a plain and on a noisy background (IoU over 0.9, found and drawn box), the session's answers, the rings and a rectangle subtracted from them.
- Browser (static page of the bundle, no sandbox): 1280×800 a noisy 900×600 picture, the disc's area within 0.1 %, in 1.4 s; Erase on it; on a phone viewport with the worker refused (page fallback) from a drawn rectangle. E28b adds the brushes and the soft edge (`plans/image-editor-steps-6.md`).
