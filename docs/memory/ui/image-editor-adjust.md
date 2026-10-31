---
name: image-editor-adjust
description: The image editor's Adjust tool: eight adjustments, nine filters as fixed values of them, one colour matrix shared by the preview and the server, the vignette and sharpening, the GD colour layer
module: image_editor
---

## What does the Adjust tool offer?
- Two tabs (Adjustments, Filters). Sliders: brightness, contrast, saturation, exposure, temperature, and under Details gamma, vignette (0-100), sharpen (0-100); the others -100 to 100, 0 changes nothing. A double click sets a slider to 0; one drag is one undo step (letting go ends it); Reset in the panel header clears the adjustments and the filter.
- Filters (none, mono, sepia, cool, warm, fade, vivid, noir, chrome) are fixed values of the same numbers, added to the sliders' and kept in range (`colorValues()`); the document keeps `adjust` and `filter` apart. The tiles show the picture with each filter over the current sliders, drawn with `applyColor()` on a small canvas.

## How is the colour computed?
- One affine matrix and a gamma, the same in `assets/js/pg_image_editor_model.js` (`colorMatrix()`) and `includes/fn/image_editor.php` (`pg_image_edit_color()`), on sRGB values from 0 to 1, in this order: exposure scales (2^(v/100)), brightness lifts (±0.25), contrast spreads around 0.5 (2^(v/100)), saturation from or towards grey (Rec. 709 luma), temperature as R/G/B gains; then gamma (exponent 2^(-v/100)).
- Temperature comes after saturation so a grey picture takes a tint: sepia is saturation -100 with warmth. Before that order the sepia tile was grey.
- Vignette: black over the picture, opacity 0 up to 40 % of the way to the corners (an ellipse through them), 30 % of the amount at 70 %, the amount (0.8 at full) at the corners. Sharpen: an unsharp mask over a 3 x 3 binomial blur, amount 1.5 at full.

## How does the preview show it?
- An SVG filter on the picture (`feColorMatrix`, `feComponentTransfer` gamma, `feConvolveMatrix`, `color-interpolation-filters="sRGB"`), set only when the numbers change (`renderColor()`); the vignette is a CSS radial gradient (farthest-corner) on a layer above the picture and below the layers, cut by the crop shape like them.
- A focused slider keeps the editor's keys (Ctrl+Z); only text fields keep their own.

## How does the server draw it?
- Order after the size: colour, sharpen, vignette, overlay, shape. Imagick: `colorMatrixImage()` with a 6 x 6 matrix (R, G, B, K, A, offset; the offset counts 0-1), `clampImage()`, `gammaImage(1 / exponent)`, `unsharpMaskImage(1, 0.85, amount, 0)`, the vignette as a small mask scaled up (FILTER_TRIANGLE) and laid over.
- GD: sharpening is the kernel through `imageconvolution()` with offset 0.5 (GD cuts to whole numbers), the vignette mask scaled with `IMG_TRIANGLE` (`IMG_BILINEAR_FIXED` left a clear mask one step dark).
- GD has no colour matrix (reason `no_color`): without Imagick (`pgImageEditorConfig.serverColor` false) the browser sends the colour layer, the picture it loaded with `applyColor()` done (JPEG 0.95 for a JPEG, else PNG), no larger than the output needs and at most 16 MP. The server checks its size and shape against the document, draws the rest from it and writes the file's own type (`edit.type`).
- Tried against the preview on both servers: regions within 1-3 levels. `tests/image_editor_adjust_test.php` holds the numbers to the model's and Imagick's colours to the browser's.
