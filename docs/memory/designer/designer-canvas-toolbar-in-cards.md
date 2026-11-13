---
name: designer-canvas-toolbar-in-cards
description: The selected element's toolbar in a card (#9217, #9246): the ratio rule skips chrome, room counts inside the clip, it shows in see-through wrappers
---

## Why was a card cover's toolbar a filled block over the picture?
- The canvas copies Bootstrap's `.ratio > *` (absolute, the box's full size) onto the element inside a see-through wrapper (`.ratio > .sd-wrap > …`). The copy said `*`, so the wrapper's toolbar got it too: a selected cover in `span.ratio.ratio-16x9` drew its toolbar as a blue block the picture's full height over the picture (180x166 on a 295x166 cover). The owner's picture 3 of #9104, the Blogdan loop of the Hello, Pinegrap home.
- Since #9217 the three ratio rules end in `_SD_FX_EL` (not `.sd-tb`, note, issue badge, chip row). Never write a canvas layout rule for a wrapper's children with `*`.

## Where does the toolbar go inside a clipping card?
- `_sdTbSpot(top, bottom, viewH, clipTop, clipBottom)`: room above or below counts inside the nearest ancestor whose overflow is not visible (`_sdTbClip(w, memo)`, the card with `overflow-hidden`). A cover at the card's top gets the 94x20 strip below the picture; above, the card cut it away and it showed nowhere. With no room inside the card either way, inside.
- Every writer passes the clip: `_sdTbFlip()`, `_sdTbFlipAll()` (one memo per pass, so each ancestor's style is read once) and `_sdPlaceChromeOf()`.

## Why did a picked card body or title show no toolbar? (#9246, CF5)
- Wrappers inside `.card`, `.card-body`, `.card-header`, `.card-footer` (and the modal, alert, nav sweep) and inside a `d-*flex` parent are see-through (`display:contents`); old rules hid their toolbar there. The card body, the title, "Read More", a review's author row: picked, they showed no toolbar and often no frame.
- The hide rules now read `> .sd-wrap:not(.sd-selected) > .sd-tb`: the selection's toolbar shows and `_sdPlaceChromeOf()` puts it at the element. A hovered one still shows none.
- `_sdPlaceSelectedChrome()` runs `_sdTbFlipAll()` on the selection first: a spot taken before the pass (a cover with no height yet) left the toolbar inside the picture with room below it.

## How is it checked?
- `tests/designer_canvas_card_edit_test.php`: the selection's toolbar in a card or flex row, the spot after the pass.
- `tests/designer_canvas_chrome_test.php`: the clip cases of `_sdTbSpot()` under Node, every writer passing the clip, the ratio rule ending in `_SD_FX_EL`.
- In a browser: open the Hello, Pinegrap template, select the Blogdan card's cover: a small strip under the picture, the picture uncovered.

Related: docs/memory/designer/designer-canvas-chrome-placement.md, docs/memory/designer/designer-canvas-absolute-position.md
