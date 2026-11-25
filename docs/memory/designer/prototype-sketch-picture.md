---
name: prototype-sketch-picture
description: The Sketch tool's reference picture (SK7): "More" in the selected sketch page's head, the picture's rows, how it lies under the boxes and is kept
---

## Where is it chosen?
- "More" (three dots, `.sd-sketch-more`, `_sdSketchMoreBtn()`) sits in the head of every sketch frame while the Sketch switch is on; CSS shows it only on the selected frame (`.sd-sketch-mode .pgc-selected`). It is no bar button.
- Its rows come from `_sdSketchMoreRows(page)`: `{caption}`, `{sep}` or `{icon, text, key, run}`. A later step (Page details, Build the page, the AI rows) adds its row there; `_sdSketchMoreMenu()` draws them like the chip's menu (`sd-proto-menu`, arrows, Home, End, Esc).
- Without a picture: "Reference picture". With one: the caption "Reference picture" over "Hide the picture" / "Show the picture", "Change the picture", "Remove the picture". Last: "Delete the page" with its key "Del", the same as Delete on the frame.
- "Reference picture" and "Change the picture" open the designer's picture chooser (`pgEditorPicker.image()`, `_sdSketchPicChoose()`).

## How does it lie under the page?
- An `<img class="sd-sketch-pic">` first in `.sd-sketch-body`: full width from the top, opacity .33, `pointer-events: none`, so boxes are drawn over it as on an empty page.
- Its height in frame units is read once when chosen (480 × natural height / natural width) and kept as `pic.h`; `_sdSketchBodyH()` grows the body to it while shown. A hidden picture does not.
- It is only for drawing: never built into the page and sent nowhere.

## How is it kept?
- On the sketch page: `pic: { src, h, off }` in the same store as the boxes (`prototype-sketch-tool.md`), cleaned by `_sdSketchClean()`. `_sdSketchPicSrc()` keeps only a site address (`/…`, not `//`) or http(s) of at most 2,000 characters without spaces, quotes or `<>`; `h` is 24 to 20,000.
- Every change (placed, hidden, shown, removed) is one step of the sketch's history: Ctrl+Z brings a removed picture back. Nothing asks.

## Can the picture be read into boxes?
- Yes, by the browser's model: "Read the picture" under the same caption (SK7b, `sketch-read-picture.md`).

## How was it checked?
- `tests/designer_sketch_test.php` (`test_designer_sketch_reference_picture`, `test_designer_sketch_more_rows`) in Node. The menu, the chooser and the picture were not run in a browser.
