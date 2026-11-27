---
name: designer-side-panel-drag-width
description: Dragging the Page Design Studio's left or right panel edge writes the panel's own width once a frame and the root --sd-left-width / --sd-right-width once on release (#11949, fixes #11945)
---

## Where does a side panel's width live?
- In `--sd-left-width` / `--sd-right-width` on the root (`style_designer.css`: defaults 250 / 336 px, 190 / 240 px at 1024 px and below). `.sd-panel-left` / `.sd-panel-right` read it for `width` and `min-width`; docked, as `clamp(240px, var(...), calc(100vw - 64px))`.
- `saveLayout()` stores it, `loadLayout()` sets it again on the root, `resetLayout()` removes it.

## What does a drag of the edge write?
- `initPanelResizes()` hands each move to `_sdPanelWidthDrag(panel, varName, dockClass)`: once a frame it sets the panel's inline `width` and `min-width` (docked, inside the same clamp). On release it removes them and writes the root property once; `saveLayout()` follows.
- Bounds: left 180 to 520 px, right 200 to 600 px.

## Why not the root property on every move?
- A custom property on the root is inherited by every element: each `mousemove` restyled the whole document (about 4,000 elements, 17,000 rules, 225 with `:has()`), 50 to 75 ms a time (#11945).
- Measured on a sandbox, 30 moves: left edge 117 ms a move before, 56 after; style recalculation 1,733 ms before, 150 after; whole-document recalculations 30 before (2,201 elements each), none after (largest 34). With the CPU slowed four times 415 ms a move before, 134 after.
- Same rule for any per-move write: never on `html` / `body` (a `body` class toggle costs about 40 ms); the bottom panel's drags (`_sdBpDrag()`) write once a frame on the bottom panel and its grid.
