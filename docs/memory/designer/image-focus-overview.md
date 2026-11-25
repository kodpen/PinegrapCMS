---
name: image-focus-overview
description: A picture's focus point ("Focus" in the options, "Set focus" on the canvas): why, what binds it, which file holds what
module: image_focus
---

## What is it, and why?
- Owner (2026-11-24): one of the seven designer additions before the launch (`workflow/decisions-made-product-106.md`): a picture cut by its frame keeps the face or the product in view on every screen. Built in PRs #11523 (value), #11576 (control), #11609 (canvas mode).
- Before it, the Image kept an object position only as a style value typed by hand; "Edit image" crops the file itself (`image-editor-tool.md`); a ratio box or a cover background cut the picture differently per screen (`image-size-props.md`, `responsive-images.md`).

## What binds it?
- Owner (2026-11-24): a picture's position gets no second picker: the Focus control is that control, also for a video when it gets one (`workflow/decisions-made-product-110.md`).
- An element with no focus set draws byte for byte as before; a page saved before needs no change.
- The site, the canvas and the Preview draw the same cut: the render and its browser twin share one writer (`image-focus-value.md`).
- The file is never changed: cropping stays "Edit image". The value is kept on the node, per place; a default kept with the picture itself would need a column and was not built.
- On a list or card bound to records, the focus set on the template is every record's; a line under the control says so.

## Which file holds what?
- The value, per screen sizes, the `pg-focus` rule, the writers: `image-focus-value.md`.
- "Focus" in an Image's and a background picture's options, the three frames, the part `focus`: `image-focus-control.md`.
- "Set focus" in the element's toolbar, the mode, its keys: `image-focus-mode.md`.
- The drawing the screens were built from: `designer-image-focus-drawing.md`.
