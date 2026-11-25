---
name: designer-scroll-snap
description: Advanced › Layout's Scroll snap on a flex or grid box and Snap to on its children (DO8): classes, rules joining User Styles on first use, the scroller kept apart
---

## Where do the rows sit?
- Style tab › Advanced › Layout: "Scroll snap" after Gap on a box that is flex or grid at any size (`_sdSnapBox()`), "Snap to" after Fill on the child of one. No new button in a bar or a head.
- Each is one value for every size (`mkNonRespRow()`), not per size. None (Scroll snap) and Default (Snap to) write nothing, so an element left alone draws as before.
- Their context keys are `snapBox` / `snapItem` of `_sdOptCtx()` (several elements: all of them); a row with `when` in `_SD_LAY_ROWS` follows that key. They have no `name`, so the note under Layout never names them. A row holding a value is drawn whatever the context.

## What do they write?
- Classes, never an inline style: `pg-scroll-snap-always` (`scroll-snap-type: both mandatory`), `pg-scroll-snap-near` (`both proximity`), `pg-snap-to-start` / `-center` / `-end` (`scroll-snap-align`). `both` snaps whichever way the box scrolls.
- The rules live in `_SD_BLOCK_CSS` and join User Styles the first time a value is picked (`_sdSnapEnsure()` → `_sdEnsureBlockStyles()`), from the single element's handler and the several elements' commit; an operator's edit of a rule is kept. Classes listed in `_SD_SNAP_CLASSES`.
- The rule sets no overflow (that would clip shadows and menus): while Scroll snap is set and the box has no Overflow Auto or Scroll (prop or Bootstrap class), a note under the row says "Snapping works once the element scrolls: set Overflow to Auto or Scroll." (`_sdSnapNeedsScroll()`).

## What about the scroller component?
- A scroller row (`pg-snap`) takes no Scroll snap row and its children no Snap to: it keeps its own `pg-snap`, `pg-snap-item`, `pg-snap-wide` rules (`designer-scroller-wide-slides.md`). The class prefixes `pg-scroll-snap-` and `pg-snap-to-` were chosen so the panel's prefix match never removes the scroller's classes.

## How is it found and tested?
- The Options search finds the rows by "Scroll snap", "Snap to" or the class prefix.
- `tests/designer_scroll_snap_test.php` (the Options tabs and Layout by context blocks under Node).
