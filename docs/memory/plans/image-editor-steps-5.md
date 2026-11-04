---
name: image-editor-steps-5
description: Steps E23b and E30 of plans/image-editor.md (owner 2026-11-03 and -04): one Vektör rail item for the vector tools, the photo's picture as its bottom layer; claim as [plan:image-editor] E<n>
---

## Which steps are there?
- Owner decisions in `workflow/decisions-made-product-24.md`. Sizes, files and review as in `image-editor-workers.md`.

- E23b (S) One Vektör rail item (owner 2026-11-03). After E24 and E25.
  - The pen, the scissors, E24's shape builder and E25's warp (E26's free deformation when it comes) share one Vektör item in the rail and the phone's tool bar: a mode switch at the top of its panel, the item highlighted in each mode; each mode keeps its hint, letter and pointer handling. The desktop rail keeps its labels at 1280×800.
  - Tests: the profiles' rail lists one item for the group; a mode switch keeps the selection. In the browser: the rail at 1280×800 with labels, the phone's tool bar.

- E30 (M) The picture as the bottom layer (owner 2026-11-04). `free`.
  - A photo's picture is the last row of the Layers panel, Arka plan, locked. Unlocked there or in the Canvas panel, it is selected, moved, resized and deleted like a layer while Crop and Adjust keep working on it; the canvas's size and the other layers stay where they are. Delete leaves the canvas with its fill and the layers (the document becomes a canvas). A photo without a canvas gets one at unlock. One undo step each; locked again it stays where it is.
  - The save keeps its path: the extend step lays the picture at its place and size on the canvas (`ui/image-editor-canvas-save.md`).
  - Tests: the model (a move, a resize and a delete keep the canvas size and the layers' places); in the browser drag, resize, delete and undo on a photo, desktop and phone, Save over it and as a copy.
