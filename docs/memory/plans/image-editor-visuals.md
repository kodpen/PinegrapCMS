---
name: image-editor-visuals
description: The image editor prototype the owner approved on 2026-10-31: its screens and source in plans/image-editor/, what each screen shows and which step of plans/image-editor.md builds it
---

## Where are they and how are they read?
- `docs/memory/plans/image-editor/NN-name.jpg`, 1280 px wide, Turkish labels; photo screens dark, canvas screens light. They fix behaviour and layout; colours, icons and spacing come from the panel's tokens.
- `plans/image-editor/prototype/*.dc.html`: the prototype's source, a design-canvas component (it needs that runtime and its sample pictures to run, which are not here). Read it for the maths, the panels, the labels and the edge cases already solved: `Main.dc.html` is the editor; the others set its props (`profile`, `tools`, `modal`).

## Which screen goes with which step?
- 01-photo (E1, E5): the layout: top bar, tool rail, layers panel, hint and zoom line; a photo with two texts and a sticker as layers.
- 02-crop (E2): crop box with thirds, ratio chips, rotate and flip tiles, straighten slider, output size, apply and cancel.
- 03-adjust (E4): adjustment sliders, the filters tab beside them.
- 04-shapes (E6): the nine draw kinds; star, polygon, bubble and a rounded box, the box selected with its corner-radius handle (the dot inside the corner) and value.
- 05-text (E7): typing in place, text panel (colour, font, size, background, bold, italic, alignment).
- 06-redact (E10): a pixelated area, modes (pixel, blur, solid box), strength, the warning that a redacted save drops older versions.
- 07-stickers, 08-frame (E8): the sticker set and an editable sticker label; frames, polaroid chosen.
- 09-vector (E14): a Bezier path with nodes and handles; smooth, corner, close.
- 10-versions (E11): the versions drawer with restore.
- 11-export (E13): format, quality, longest edge, metadata, size before and after.
- 12-annotate, 13-arrow (E1, E9): `tools` subsets: a mini bar with five tools on a screenshot; arrows only.
- 14-canvas-new, 15-canvas (E12): New image presets and background; a canvas with a picture, a sticker and a text layer.
- 16-artboard-drag, 17-artboard-done (E12): the artboard tool: dashed new bounds and the size while dragging, anchor grid, fit to content, preset sizes; after release the board is wider and the artwork has not moved.
- 18-modal, 19-fullscreen (E1): the modal over the File Manager; the same editor after the full-screen button.

## What is in the prototype but not on a screen?
- Undo and redo across every tool, rotate and flip with layers following, crop reset, the save menu (new version, copy, export settings), dark and light themes, Turkish and English labels.
