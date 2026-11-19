---
name: designer-canvas-chrome-placement
description: Designer chrome that must not move or cover the canvas: the context bar's reserved row, the toolbar spot above/below/inside (_sdTbSpot), the page root's name tag at the bottom-left, the size badge.
---

## Why does the context bar keep an empty row?
- `#sd-ctx-bar` (Dropdown / Navbar / Nav / Accordion / Modal actions, `updateCtxBar()`) sits between the view bar and the canvas. It used to go from `display:none` to shown when a press selected an element, so the canvas moved down 32-35 px between the press and the release: the release and the click landed on another element, which selected that one and hid the bar again. The first accordion question, the annual pricing pill and a catalog detail tab could never be opened by a click, and the first double-click on a carousel caption did not edit.
- Now the row is always there: one line, `height:35px`, `flex-wrap:nowrap`, options that do not fit scroll sideways (`overflow-x:auto`). With nothing to show, `_sdCtxBarIdle(bar, true)` adds `sd-ctx-idle`, which hides only its contents (`visibility`). Never show or hide the bar with `display` again, never let it wrap, never defer it with a timer.
- It does not fit into the view bar's row: at 1366 px the view bar is already wider than the canvas (952 px of controls in 856 px) and the Navbar options alone take 555 px.

## Where does an element's toolbar go?
- `_sdTbSpot(top, bottom, viewH, clipTop, clipBottom, nextTop)`, from the wrapper's box (`_sdBoxRect()`) and the viewport height: `above` when `top >= 22`; `inside` (`top:2px`, `sd-tb-inside`) when the bottom leaves no 22 px (a section taller than the viewport); `below` (`top:calc(100% + 2px)`, `sd-tb-below`) only when the strip fits before the next element (`nextTop >= bottom + 22`); else `end` (`sd-tb-end`, `top:2px` at the right end).
- Why `end` (#10275): below ignored what follows, so a heading with a 4 or 16 px margin at the canvas top had its name tag and buttons over the paragraph's first words, taking their clicks. The right end keeps both lines' starts free.
- `nextTop`: `_sdTbNextTop(w, bottom)`, the first following sibling (then an ancestor's) starting at or under the bottom, chrome and absolute/fixed skipped; measured by `_sdTbSpotOf()` only with no room above.
- Inside at the left was once the only answer for the top: the toolbar lay over the first line, so a navbar brand or first breadcrumb could not be double-clicked (the second click hit Duplicate or Add note).
- Every writer uses it through `_sdTbSpotOf()`: `_sdTbFlip()` (hover), `_sdTbFlipAll()` (`_sdOnCanvasScroll()` and `adjustCanvasToolbars()`, all measured then written, only changes written) and `_sdPlaceChromeOf()` for see-through and unpositioned wrappers (designer-canvas-absolute-position.md).
- Inside a clipping card and in a ratio box: [[designer-canvas-toolbar-in-cards]].
- The size badge (`_positionSizeBadge()`) moves after a toolbar drawn below.

## Where is the page root's name tag?
- `body > .sd-wrap[data-sd-type="root"] > .sd-tb` is `position:fixed` at the bottom-left of the canvas viewport (it beats both spot rules). At the top-left it covered whatever starts the page (a theme switch, a brand) whenever the pointer was over the page between elements, and took those clicks. A widget's own root keeps the normal spots.

## How is this checked?
- `tests/designer_canvas_chrome_test.php`: `_sdTbSpot()` under Node (clip cases, the issue's next-element measures), the writers, the CSS rules and that `updateCtxBar()` never touches the bar's `display`.
- In a browser: with nothing selected, click the first FAQ question, the annual pill, a tab: each opens on the first click; the iframe's top stays put. Put a navbar or breadcrumb first on a page, click then double-click the brand: in-place edit starts, the tree keeps its node count, `elementFromPoint` at the text is the text.

Related: docs/memory/designer/designer-canvas-redraw-paths.md, docs/memory/designer/designer-canvas-absolute-position.md
