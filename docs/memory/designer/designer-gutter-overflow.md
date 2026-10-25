---
name: designer-gutter-overflow
description: Why wide row gutters (g-5) overflow inside .container on phones, the g-4 g-lg-5 rule, and how to measure responsive overflow of a block.
---

## Why does `g-5` overflow inside `.container`?
- `g-5` gives the row -24 px side margins; `.container` padding is 12 px. Below 576 px `.container` is full width, so the difference lands outside the viewport and **the page scrolls sideways on phones**.
- Above 576 px `.container` has a `max-width` and edge space, which swallows the overflow: the bug shows only on narrow screens, invisible to anyone looking at desktop.
- **Rule:** row gutter `gutter: '4'`; for a wide gutter use `cssClass: 'g-lg-5'`. Never plain `gutter: '5'`.

## How is responsive overflow measured?
- A palette double-click puts the block **into the selected node** when it takes it, else right after it (`palette-insert-at-selection.md`). A selected section takes the next block, so consecutive double-clicks still nest blocks and fake an overflow. Measure each block alone: drop -> take the HTML generated from `#sd-html-tree` (`&nbsp;` inside tags becomes a normal space) -> undo with Ctrl+Z.
- Measure on that HTML in a clean Bootstrap page, not on the canvas: canvas `.sd-wrap` wrappers do not exist in the real page.
