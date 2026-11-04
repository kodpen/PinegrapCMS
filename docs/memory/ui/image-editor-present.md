---
name: image-editor-present
description: Present (AN8): a canvas's scenes full screen, keys, swipes, transitions, reduced motion; PDF in Save animation, a JPEG page per scene from M.pdfFile()
module: image_editor
---

## What is Present?
- Part `present` (`pg_image_editor_present.js`, labels `pg_image_editor_strings_present()`, model section `PRESENT`). A Present button in the top bar on a canvas with two scenes or more (`presentOffered()`); editor only, nothing on the live site (owner, #4714).
- It opens `.pgie-present` over the editor and asks the browser for full screen on it; without the Fullscreen API the box fills the editor. Leaving the browser's full screen (its own Escape) closes Present; the editor's own full screen stays.

## How does it move between scenes?
- `M.presentKey()`: Space, Enter, Right, Down, Page Down next; Left, Up, Page Up, Backspace previous; Home, End; Escape closes. A focused button keeps its own Enter and Space. Its keydown never reaches the editor.
- `M.presentGesture()`: a press that moved under 10 px is a tap (next), a mostly sideways move of 40 px or more a swipe (left next, right previous). No wrap at either end (`M.presentGo()`).
- Each scene plays its motion once and holds its end. Moving forward draws the whole animation's moment over the previous scene's transition (`M.presentTime()`), so the transition shows; going back or jumping shows the scene alone. With `prefers-reduced-motion: reduce` every scene is drawn at rest.
- Frames come from `renderFrame()` on the visible canvas, letterboxed by `M.presentFit()`, at most 2560 px on the long edge; a frame still drawing lets the next animation frame pass.

## How is the PDF made?
- `ANIM_FORMATS.pdf` with `needs: 'scenes'`: the dialog passes `scenes` in the caps, so PDF is offered only with two scenes or more; a chosen PDF falls back to GIF once the scenes are gone. No rate or repeat chips; the estimate and the progress note say pages.
- `animJob()` hands a PDF to `pdfJob()`: each scene at rest (`renderFrame(null, …, M.sceneView())`) on white, `toBlob('image/jpeg', 0.9)`, then `M.pdfFile(pages)` on the page (no worker). A page is the picture's size at 96 px an inch; the JPEG goes in as `DCTDecode` unchanged.
- The estimate encodes the middle scene's page and multiplies. Save to Files needs no server change: action `animation` already tells a PDF by `%PDF-` and keeps it under `.pdf`.

## Where is it tested?
- `tests/image_editor_present_test.php`: the fit, keys, gestures and moves, the moment with its transition, the PDF read back (xref offsets, page sizes in points, the JPEGs byte for byte, `pg_image_editor_animation_type()`), PDF offered only with scenes, the labels.
