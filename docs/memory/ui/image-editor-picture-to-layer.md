---
name: image-editor-picture-to-layer
description: Convert to a layer in the Design Canvas (#9198): the photo as a picture layer at the bottom of a canvas, so layers can go under it; what goes into its pixels, where offered, saving
---

## What does it do?
- Convert to a layer (`pictureToLayer()`, artboard part) turns the photo into a picture layer, as other editors turn a background layer into a normal one. The server draws the picture without its layers (action `base` with `png`: crop, turns, colour, redaction, erased and adjusted areas, a crop shape's clear corners). The browser keeps it as a data: PNG (a JPEG when nothing is see-through) at most 4096 px a side.
- `M.pictureLayer()` makes the document an artboard of the canvas, its fill kept (`M.dropPicture()`), with that layer at the bottom where the picture lay. Other layers can then go under it with Order, Send to back, and show through what is see-through.
- The layer's id is one none of the photo's layers had: after an undo the selection kept by id must not land on an area the drawn picture took in.

## What is lost and what comes back?
- Crop, Adjust, Redact, Select area and the other photo tools no longer apply: their work is in the layer's pixels, and the rail becomes the canvas's. The selection and the erased, adjusted and redaction areas go.
- One undo step brings the photo back with all of it editable. An edit made while the server draws leaves the photo as it is.

## Where is it offered?
- The Background row of the layers list (an icon button beside the lock, locked or not), the selected picture's panel, its selection bar and its context menu (`M.actionList()` with `can.pictureLayer`). Only where the server can draw the picture: the editor's API and a file (`pictureLayerOn()`).
- A failure shows "Sorry, the picture could not be made a layer." or the server's refusal.

## How is it saved?
- As any canvas (`pg_image_editor_save_canvas()`): over the file only when it is a PNG, else as a copy. The layer's picture becomes its own file in the folder as pictures from the computer do, named after the photo and Background (`cover-Arka_plan.png`) so it does not take the copy's name. The copy keeps its document and opens again with the layers.

## Why this way?
- Owner (2026-11-08): reporting that an added picture could not be sent under the photo, he chose Convert to a layer (one command, a small change) over keeping the photo in the layer order with its own tools (stage, save, animation and SVG export reworked, two PRs).

## How is it checked?
- `tests/image_editor_picture_test.php`: `M.pictureLayer()`, and the base drawn as a PNG keeping a round crop's corners clear; `tests/image_editor_actions_test.php`, `tests/image_editor_menu_test.php`: where it is offered.
- Browser (sandbox, 2026-11-13): a JPEG with its outside erased and a picture added, Convert from the Background row, Send to back under it, undo and redo, saved as a copy and opened again with both layers; the row at 390 px.
