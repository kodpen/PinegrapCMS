---
name: designer-canvas-chrome-placement
description: Designer chrome that must not move or cover the canvas: the context bar's reserved row, the toolbar spot above/below/inside (_sdTbSpot), the page root's name tag at the bottom-left, the size badge.
---

## Why does the context bar keep an empty row?
- `#sd-ctx-bar` (Dropdown / Navbar / Nav / Accordion / Modal actions, `updateCtxBar()`) sits between the view bar and the canvas. It used to go from `display:none` to shown when a press selected an element, so the canvas moved down 32-35 px between the press and the release: the release and the click landed on another element, which selected that one and hid the bar again. The first accordion question, the annual pricing pill and a catalog detail tab could never be opened by a click, and the first double-click on a carousel caption did not edit.
- Now the row is always there: one line, `height:35px`, `flex-wrap:nowrap`, options that do not fit scroll sideways (`overflow-x:auto`). With nothing to show, `_sdCtxBarIdle(bar, true)` adds `sd-ctx-idle`, which hides only its contents (`visibility`). Never show or hide the bar with `display` again, never let it wrap, never defer it with a timer.
- It does not fit into the view bar's row: at 1366 px the view bar is already wider than the canvas (952 px of controls in 856 px) and the Navbar options alone take 555 px.

## Where does an element's toolbar go?
- `_sdTbSpot(top, bottom, viewH)` decides from the wrapper's box (`_sdBoxRect()`) and the canvas viewport height: `above` when `top >= 22`; `below` (outside the box, `top:calc(100% + 2px)`, class `sd-tb-below`) when the element is at the canvas top and its bottom leaves 22 px; `inside` (`top:2px`, class `sd-tb-inside`) only when it reaches the bottom too (a section taller than the viewport).
- Inside was the only answer for the top before: the toolbar lay over the element's first line, so a navbar brand or the first breadcrumb could not be double-clicked for editing, and the second click of the double-click hit Duplicate (the link was copied) or Add note.
- Every writer uses it: `_sdTbFlip()` (hover), `_sdTbFlipAll()` (`_sdOnCanvasScroll()` and `adjustCanvasToolbars()`, all measured then written, only changes written) and `_sdPlaceChromeOf()` for see-through and unpositioned wrappers (designer-canvas-absolute-position.md).
- The size badge (`_positionSizeBadge()`) moves after a toolbar drawn below, so it does not sit on its buttons.

## Where is the page root's name tag?
- `body > .sd-wrap[data-sd-type="root"] > .sd-tb` is `position:fixed` at the bottom-left of the canvas viewport (it beats both spot rules). At the top-left it covered whatever starts the page (a theme switch, a brand) whenever the pointer was over the page between elements, and took those clicks. A widget's own root keeps the normal spots.

## How is this checked?
- `tests/designer_canvas_chrome_test.php`: `_sdTbSpot()` under Node, the writers, the CSS rules and that `updateCtxBar()` never touches the bar's `display`.
- In a browser: with nothing selected, click the first FAQ question (opens on the first click), the annual pill, a tab; the iframe's `getBoundingClientRect().top` stays the same across selections. Put a navbar or breadcrumb first on a page, click then double-click the brand: in-place edit starts, the tree keeps its node count, `elementFromPoint` at the text is the text.

Related: docs/memory/designer/designer-canvas-redraw-paths.md, docs/memory/designer/designer-canvas-absolute-position.md
