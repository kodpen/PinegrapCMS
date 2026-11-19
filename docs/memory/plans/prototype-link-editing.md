---
name: prototype-link-editing
description: Plan (owner 2026-11-19): the Prototype view as a place to edit links: a target picker on the links panel's rows, arrows starting on the link, the selected page at full height; PL1-PL4
---

## What did the owner ask?
- Owner (2026-11-19, in the architect's window, agent channel message 9492), on the designer's Prototype view, with a picture of it, the home page selected: "prototipten sayfa bağlantılarını düzenlemek çok zor. mesela ana sayfada +2 bağlantı diyor ama o alandaki bağlantılara erişemiyorsun bile. ayrıca oklar bağlantının üzerinden de başlamıyor".
- The view shows what leads where, and that stays; what he cannot do with ease is change a link from it.

## What stands in the way today? (read in the code on 2026-11-19, not tried)
- The foot "+2 links below" is text: a page's picture is cut at 1600 px and a link under the cut has no mark (`designer/prototype-page-picture.md`). It can be changed from the links panel only.
- A panel row shows its target as grey text; "Change target" is one of two 24 px icon buttons at the row's end (`_sdProtoListDraw()`), and in his picture the panel's right side lies beyond the picture's edge.
- An arrow leaves the frame's edge level with its mark; the line between the mark and the edge is not drawn (`designer/prototype-marks.md`).
- A 1280 px page is drawn 228 px wide, so a button's mark is a few pixels and a drag from it is fine work.

## What is built?
- PL1 (done, #10394), the panel as the place to edit. An outgoing row's target is seen as a control: the target's name in a box with a caret, opening today's target menu (`_sdProtoMenuOpen()`); a link without a target reads "Choose a page". "Open in the editor" stays an icon. A row that may not be drawn (K2) keeps its target as text. The rows are checked at 1100, 1400 and 2000 px: nothing cut at the panel's right edge.
- PL2 (done, #10414), the arrow starts on its link. For each arrow shown the view draws the leg from the mark's middle to the arrow's port on the frame's edge and a dot on the mark, in the arrow's colour, lifted and faded with it (`_sdProtoLift()`, `_sdProtoDrawing()`). The leg is the view's own, drawn in the frame; the core is not changed.
- PL3 (done, #10424), the selected page at full height (prototype-page-whole.md). The foot becomes a button: "Show the whole page" opens the selected frame's picture to the page's height (at most 6400 px of page), every link gets its mark, and the frames below move down as they do for a measured shared part (`_sdProtoPicSettle()`). "Show less", or another frame selected, closes it. One frame open at a time; nothing is stored.
- PL4 (in progress), a handle to pull. The pointer or the focus on a drawable mark of the selected frame shows a round "+" handle beside it, 20 px on screen at any zoom; a drag from it draws as a drag from the mark does. A mark smaller than 12 px on screen takes presses 4 px around it.
- Order PL1, PL2, PL3, PL4, one PR each; no schema, no endpoint. Each keeps K1 to K9 (`designer/prototype-view-rules.md`) and adds its tests to `tests/proto_hotspots_test.php`.

## What is decided?
- The owner's words: `workflow/decisions-made-product-78.md`. The view he accepted on 2026-11-17 (`workflow/decisions-made-product-70.md`) stays; the steps change how a link is reached and changed.
- Architect (2026-11-19): the four steps are the reading of his words and change on his look. PL3 ends "every frame keeps one height" (agent channel message 6079) for the one open frame only.

## How does the plan close?
- `designer/prototype-list.md`, `designer/prototype-marks.md`, `designer/prototype-page-picture.md` and `designer/prototype-drawing.md` say the new state and this plan is deleted.
