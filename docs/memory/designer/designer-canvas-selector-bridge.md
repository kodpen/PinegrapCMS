---
name: designer-canvas-selector-bridge
description: Canvas selector bridge for designs on their own framework: rules rewritten across .sd-wrap, only rules whose classes are on the page, flat selector lists instead of :is(), one place that rewrites.
---

## What is the bridge for?
- On a design that does not use the bundled Bootstrap (`_sdFxFlatOn()`), wrappers are see-through (`display:contents`). The design's own child, sibling and position selectors (`.navbar > .container`, `li:first-child`, `.btn + .btn`) would miss the elements, so `_sdBridgeSheets()` rewrites them in the canvas copy of its stylesheets to also step over one wrapper level and a loop area.
- Only the canvas copy changes. The rule keeps its place in the cascade and its specificity (the added parts sit in `:where()`); a rewrite the browser rejects leaves the rule as it was.
- The bundled Bootstrap design uses hand-written imitation rules instead (`_SD_BX`, designer-canvas-style-designer.md).

## Which rules are bridged?
- Only rules the page can use. `_sdBrNeeds(selector)` lists the classes each selector needs outside brackets; while one is missing on the canvas the rule waits in `canvasDoc._sdBr.wait` (keyed by a missing class), and the editor's `MutationObserver` bridges it the moment the class appears (`_sdBrRelease()`). A selector that needs no class is bridged at once; without an observer everything is.
- Why: a framework stylesheet carries thousands of rules for classes a page never uses (every `space-x-*`, `row-cols-*` step per breakpoint). Bridged for nothing, they took every canvas style recalculation from about 30 ms to 250 ms on Bootstrap 6, and hovering and dragging stalled.
- A new path that brings classes to the canvas needs no extra work. `_sdBridgeRule()` is the only place that rewrites a rule.
- A sheet walked before with the same rule count and no `@import` still arriving is not walked again.

## What shape does a bridged selector take?
- The last step stays a plain selector list, never one `:is()`: each alternative keeps its own rightmost compound, so the browser files the rule under that class or tag and tests it only on elements that carry it. Wrapped in `:is()`, every bridged rule sat in the catch-all bucket and was tested on every element at every style change.
- All alternatives keep the original's specificity (what they add is inside `:where()`), so the cascade is unchanged. Only a list the browser refuses as a whole falls back to the forgiving `:is()` form.

Related: docs/memory/designer/designer-canvas-redraw-paths.md
