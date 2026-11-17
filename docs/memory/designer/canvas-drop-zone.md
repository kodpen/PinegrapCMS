---
name: canvas-drop-zone
description: Canvas drop bands (before, inside, after) by pointer height; a closed element's middle band falls back beside it; when an ancestor takes over; one insert line at a time.
---

## Which band of an element does a canvas drop use?
- `_getZone(e, box)` in `setupDropEvents()` (style_designer.js): top 28 % of the height "before", bottom 28 % "after", the middle "inside"; under 20 px only before/after by half. The root is always "inside"; a link or button that takes the drag splits left/right into "inside-start"/"inside-end"; children of a link or button split left/right into before/after.

## What if the element or its parent refuses the drag?
- `_sdCanvasDropZone(zone, y, h, canInside, canSibling)` decides, the same rule as the Overview tree:
  - Middle band refused by the element: beside it on the nearer edge (upper half "before", lower half "after") when its parent takes the drag.
  - Before/after refused by the parent: "inside" when the element takes it (a region over the top of a col: the row takes no region).
  - Neither: `null`; the dragover bubbles and an ancestor measures the pointer against its own box.
- canSibling asks the main-tree parent, else the parent in a shared component's cached tree (`_findParentInShared()`).

## Why does the middle band no longer bubble?
- Until the fix for issue #10066, a middle-band drop on an element that takes no children (the Messages block, an image) bubbled to an ancestor; a tall container read the pointer as its own top band, so the insert line sat on a far, often off-screen edge and the item landed there.

## Where is it tested?
- `tests/designer_drop_zone_test.php` cuts `_sdCanvasDropZone()` out of the source and runs it under Node (skipped without Node).

## How many insert lines can show at once?
- One. `_sdSetDropMark(el, mark)` remembers the marked element (`_sdDropMarkEl`); a mark on another element first clears the old one and its `_sdDropZone`. An element that takes the drag stops the dragover, so an ancestor marked a moment earlier is never reached again to clear itself: two lines showed until the pointer left it (issue #10071). Tested in `tests/designer_drop_mark_test.php`.
