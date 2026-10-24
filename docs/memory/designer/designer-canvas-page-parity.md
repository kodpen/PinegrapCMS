---
name: designer-canvas-page-parity
description: Canvas vs published page of a Bootstrap design: width buttons give the canvas their width, Bootstrap rules rebuilt across wrappers, sd-fx-fit, what still differs on purpose.
---

## Why did the 768 button draw the SM layout?
- The width buttons and the resize handle set `#sd-iframe-wrap`'s width; its 1px side borders took 2px of it, so "768px · MD" was a 766px canvas and Bootstrap laid out the SM breakpoint (576, 992 and 1200 too). Blocks stood up to 1156px taller at 768. The side lines are box-shadows now (`style_designer.css`), so the label and the canvas agree.

## Which Bootstrap rules does the canvas rebuild across the wrappers?
- Every child sits in a wrapper of its own, so a rule that finds a child by its place (`.row-cols-2 > *`, `.row > *`, `x + x`, `:first-of-type`, `.form-floating > label`) missed it. `_SD_BX` rules in `IFRAME_CSS` put them on the element:
  - row-cols widths at every breakpoint (`_sdRowColsCss()` over `_sdBreakpointCss()`), skipping an element with any `col-*` class at every width (a responsive one below its breakpoint still differs from the page). Logo, team and picture grids had six columns at 375px where the page has two.
  - `.row > *` full width with zero specificity (`:where()`), so a `col-*` class still beats it.
  - breadcrumb divider, list group seams and flush borders (horizontal lists at every breakpoint), the accordion's first and last item, the floating label over a full-height control. The label is not lifted when selected and does not move on focus, or the second click of a double-click missed its text.
  - sides in logical terms (`padding-inline-start`, `float:inline-start`), so an RTL design is mirrored.
- `.sd-wrap.d-none` hides only the editor's own wrappers; a column's wrapper is the column, its `d-none d-lg-block` is Bootstrap's (the picture column never showed).
- Inline wrappers (`sd-wrap-inline`, icon, link, badge) sit on the baseline without margin; btn/button ones stay middle-aligned, without margin; an inline `.w-100` gets a block wrapper (the sign-in button was as narrow as its label).

## What is sd-fx-fit?
- Under the 28px empty-wrapper minimum Chromium drops the bottom margin a shorter element passes through its wrapper: price-list `li.mb-2` lines stood 8px closer (pricing-tiers 56px short at 375px). `_sdCanvasFidelityPass()` measures it (`_sdFxFits()`: shown, in the flow, bottom margin, from 12px (`_SD_FX_FIT_MIN_GRAB`) up to the minimum, wrapper without bottom padding or border) and sets `sd-fx-fit` (`min-height:0`).
- An empty or thinner element keeps the minimum as its handle: a divider (`hr`, 1px) shrank to its line and could not be picked.
- Heights are CSS px: the rect is divided by the wrapper's `currentCSSZoom` (the canvas zoom); undivided, at 150% the items lost the class.
- The pass runs after each render and through `_sdFxSoon()`: on a canvas window resize (width buttons, handle, window, docking), a stylesheet or picture load, and a zoom change (`setCanvasZoom()`). Zoom rewraps lines without a resize event; uncalled, pricing-tiers stayed 7px short until a render.

## What still differs on purpose?
- The editor's spacing: container/area wrappers `padding:8px 4px; margin-bottom:6px`, block content/component wrappers `margin:3px 0`, the 28px (column 36px) minimum. A section is about 25-30px taller.
- A visually hidden label keeps its wrapper box, the only handle (designer-canvas-absolute-position.md): such a form is 24px taller.
- An inline-block wrapper does not break across lines, so a long link in running text moves to the next line at 375px; canvas siblings have no whitespace between them, so inline icons sit 4px closer.

## How was it measured?
- All 126 Blocks items on one design, published; the canvas at exactly 375/768/1280px and the logged-out page at the same viewport; box trees compared without editor wrappers and chrome, lazy images loaded. With CSS zeroing the spacing above, 82 of 378 block-width pairs differed by more than 2px before the fix, 8 after (the two kinds above); #1492.
