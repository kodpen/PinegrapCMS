---
name: designer-canvas-open-state
description: A modal, offcanvas or dropdown menu open on the canvas is canvas state only: renderCanvas() keeps it through a redraw, the tree never holds it, preview and code view show it closed.
---

## Which open state is canvas-only?
- Modal, offcanvas and dropdown-menu (`_sdShowIsCanvasOnly(cssClass)` in `assets/js/style_designer.js`). Their `show` is never authored: the save drops it (designer-collapse-open-state.md).
- A collapse is not: its open state is authored on its toggle, and the canvas still mirrors a clicked panel into the tree before a render (`_syncToggleableShowStateFromDom()`, collapse only); the save sets it back.

## What was wrong before?
- `render()` copied every live `.show` (collapse, modal, offcanvas, menu) into `props.cssClass` so a rebuild kept it. A redraw while a menu was open (Ctrl+Z, a prop change of an item) wrote `show` into the menu node; closing it on the canvas (Escape, the toggle, `_sdDdCloseAll()`) changed the DOM only. The unsaved tree, the code view and the preview kept the menu open: the preview opened with it shown and the first click closed it. The save dropped it, so only the published page was right.
- The context bar's Show (modal, offcanvas, menu), the dropdown's Open/Close and the Options panel's Expanded switch wrote `show` into the tree too, and the palette's offcanvas is built with `show`: the preview showed each of them open.
- After a page switch the mirror ran against the new page's tree with the old page's canvas ids: a menu open on page one gave `show` to page two's node of the same id (any element, e.g. a button), and that one was saved.

## How does the canvas keep it now?
- `renderCanvas()` notes the open ones before emptying the body (`_sdCanvasOpenCapture(doc)`, keyed `<widget sid>:<node id>`), builds the page from the tree, then `_sdCanvasOpenRestore(doc, open, _sdFindNodeForEl)` opens them again and takes a `show` the tree still holds off the node (a palette offcanvas, markup typed in the code view) while its element keeps it.
- Only for the page drawn before: `canvasDoc.body._sdDrawnPage` holds `_activeKey` of the drawing; after a switch nothing is captured and the collapse mirror waits for the next render.
- Elements without a wrapper (server HTML of widgets, custom HTML) are left alone. A fresh canvas document (realm reset) starts closed.

## Which controls read and write the canvas instead of the tree?
- `ctxBarToggle()` (Show / Hide) writes the tree for a collapse only.
- The dropdown's context bar Open/Close and the Options panel's Expanded switch read and set the menu element (`getCanvasInnerEl()`); neither adds an undo step any more.
- The canvas toggle click, Escape and the toolbar's eye button were already DOM-only.

## What tests cover it?
- `tests/designer_collapse_state_test.php`: the kinds, capture and restore on a DOM stand-in (a widget node apart from the page node of the same id, server HTML and collapse left out, the tree loses `show`), and where `renderCanvas()`, the mirror and the context bar call them.
