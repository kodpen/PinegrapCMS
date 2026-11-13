---
name: designer-canvas-toolbar-in-cards
description: A selected element's toolbar in a ratio box or a clipping card (a blog card's cover in a loop): the ratio rule skips chrome, room counts inside the clipping ancestor (#9217)
---

## Why was a card cover's toolbar a filled block over the picture?
- The canvas copies Bootstrap's `.ratio > *` (absolute, the box's full size) onto the element inside a see-through wrapper (`.ratio > .sd-wrap > …`). The copy said `*`, so the wrapper's toolbar got it too: a selected cover in `span.ratio.ratio-16x9` drew its toolbar as a blue block the picture's full height over the picture (180x166 on a 295x166 cover). The owner's picture 3 of #9104, the Blogdan loop of the Hello, Pinegrap home.
- Since #9217 the three ratio rules end in `_SD_FX_EL` (not `.sd-tb`, note, issue badge, chip row). Never write a canvas layout rule for a wrapper's children with `*`.

## Where does the toolbar go inside a clipping card?
- `_sdTbSpot(top, bottom, viewH, clipTop, clipBottom)`: room above or below counts inside the nearest ancestor whose overflow is not visible (`_sdTbClip(w, memo)`, the card with `overflow-hidden`). A cover at the card's top gets the 94x20 strip below the picture; above, the card cut it away and it showed nowhere. With no room inside the card either way, inside.
- Every writer passes the clip: `_sdTbFlip()`, `_sdTbFlipAll()` (one memo per pass, so each ancestor's style is read once) and `_sdPlaceChromeOf()`.

## How is it checked?
- `tests/designer_canvas_chrome_test.php`: the clip cases of `_sdTbSpot()` under Node, every writer passing the clip, the ratio rule ending in `_SD_FX_EL`.
- In a browser: open the Hello, Pinegrap template, select the Blogdan card's cover: a small strip under the picture, the picture uncovered.

Related: docs/memory/designer/designer-canvas-chrome-placement.md, docs/memory/designer/designer-canvas-absolute-position.md
