---
name: designer-image-focus
description: Plan (owner 2026-11-24): a picture's focus point set by dragging, per screen size, for an Image and a background picture, with the three screen frames shown; steps IF1 to IF4
priority: 7
---

## What is asked?
- Owner (2026-11-24): the seven additions go in (`workflow/decisions-made-product-106.md`). This one: a picture cut by its frame keeps the face or the product in view on every screen.

## What is there today?
- The Image element keeps an object position only as a style value typed by hand, and "Edit image" crops the file itself (`designer/image-editor-tool.md`); a ratio box or a cover background cuts the picture differently per screen (`designer/image-size-props.md`, `designer/responsive-images.md`). Nobody points at what must stay.

## What is built?
- "Focus" in the Image element's options and in a background picture's: the whole picture small, with a point to drag; the canvas follows as it moves. Beside it three small frames (phone, tablet, desktop, in the shapes this element has) show the cut in each.
- "Set focus" in the element's bar on the canvas: the picture shows whole, dimmed outside its frame, and is dragged under the frame as on a phone; arrows move it by one percent, Enter keeps, Escape puts it back.
- The value is a pair of percentages written as the object position (the background position for a background). A value typed before (a pair of words such as `center top`) reads as its percentages and is written back unchanged until moved.
- Per screen size: a focus set while the canvas bar shows a smaller screen is that screen's own; "Same on every screen" removes the others.
- On a list or card bound to records, the focus set on the template is every record's; one line under the control says so.

## What must it keep?
- An element with no focus set draws byte for byte as today; a page saved before needs no change.
- The site, the canvas and the Preview draw the same cut: the render and its browser twin share one writer.
- The file is never changed by this: cropping stays "Edit image". Kept on the node, per place; a default kept with the picture itself would need a column and is not in this plan.

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- IF1 (S) The value, pure, PHP and its browser twin: the pair read and written, word pairs read, per screen size, the background's twin; site render. Done: #11523.
- IF2 (M) after IF1 and DD10: the Focus control with the three frames. Done: #11576.
- IF3 (M) after IF2: "Set focus" on the canvas, the keys, background pictures. Done: #11609.
- IF4 (S) Close-out: memory in `designer/`, this file deleted, the `[NEW]` line named.

## What does every step keep?
- No table or column. The drawing: `designer-drawings.md`. Claim as `[plan:designer-image-focus] <step> — <topic>`.
