---
name: designer-spacing-box
description: Style › Spacing as one box (DP7): outer and inner sides typed, dragged or stepped, Shift and Alt, the screen-size chooser in the head, the part 'spacing', what it writes (today's classes) and reads
---

## What does Style › Spacing show?
- One box drawing: "outer" (margin) and "inner" (padding), four sides each, the core reading "content" (several elements: "3 elements"). Today's ten rows (Padding, Padding Start…, Margin End) stay in the body, hidden (`.sd-sp-rows`), so the section's count, the row reset and the search read them as before.
- The screen-size chooser XS, SM, MD, LG, XL, XXL sits in the section's head while it is open; a size holding a margin or padding class of any selected element is drawn lighter. The chosen size (`_sdSpSize`, XS first) holds for every element until the editor closes.

## Why six sizes when D17 names five?
- Today's rows write the XXL forms too (`mt-xxl-3`); a chooser without XXL could not show or change them (K1). The canvas bar has six sizes as well.

## How is a side changed?
- Click: a field in its place; type 0 to 5, "auto" (margins only; "oto" is read too), empty for none; Enter or leaving writes, Escape gives the number back; an invalid value is framed red and not written.
- Drag up or down: one step per 8 px, written once on release. Arrow Up / Down step; Delete or Backspace clears.
- Shift writes the opposite side too; Alt writes all four as the one all-sides class (`m-3`, `p-md-2`) in place of every class of that kind at that size. Each change is one undo step.
- Grey: inherited from a smaller size or the default (the tip names the size); filled accent: set at the chosen size; a frame: being typed; "~" in an accent frame: the selected elements differ.

## What does it write and read?
- `assets/js/pg_spacing_box.js` (window.PGSpacingBox), pure: `write()` writes only today's classes (`mt-*`, `mb-*`, `ms-*`, `me-*`, `pt-*`, `pb-*`, `ps-*`, `pe-*`, `m-*`, `p-*` and their size forms); other classes keep their place, new ones go to the end, as today's rows add them.
- `read()` applies the classes as the browser does: a side class over `mx-*` / `my-*` over the all-sides class, a larger size over a smaller one. `mx-*`, `my-*` and negative margins (`mt-n2`) a page holds are read and kept.
- Clearing a side set by `m-3` or `my-2` of that size removes that class and writes the other sides it set as side classes, so only the cleared side changes.
- Unlike today's base select (it also removed `mt-md-*` when `mt-` changed, matching by prefix), a change at one size never touches another size's class.

## How is it loaded?
- The part `spacing` (`assets/js/style_designer_spacing.js`, bundle `style_designer_spacing.min.js` = the module, the corner module of Style › Border and corner (`designer-corner-box.md`), then the part; `pg_designer_parts()`). The section body holds `<div class="sd-spb-host sd-bm-wrap" data-sd-spbox>`; `_sdSpBoxBind(nodes)` (single and several-element panel) runs `_sdPartCall('spacing', 'draw', [host])` once the section is shown; a closed card's body is not drawn, so the part loads on first open.
- The host (`_sdSpBoxHost()`) gives the classes, the size, `locked` (content level) and `commit(fn)`: one element `saveState()` and `_sdRenderNode()`, several `_sdMcCommit()`; view mode nudges.
- A design without Bootstrap removes the box with the rows (`.sd-bm-wrap`, `_sdHideBootstrapControls()`). The old click-to-cycle box (`boxModel()`, `.sd-bm-v`) is gone.

## Where is it tested?
- `tests/designer_spacing_box_test.php`: every side at every size and value written as today's row's class and read back; Shift and Alt; auto on margins only; a class list saved by today's rows read back; clearing and keeping; the part's wiring.
- Not unit-tested: the drawing, drag and focus in a browser; the 240 px panel (side paddings 27 by a container query).
