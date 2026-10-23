---
name: designer-canvas-redraw-paths
description: Which designer path redraws what: render() for tree changes, _sdSelectRender() for a selection, the overview marked in place, the scroll pass, toolbars filled on first need, writes by difference.
---

## Which call redraws the canvas?
- `render()` is for paths that change the tree: it rebuilds the canvas DOM (`renderCanvas()`) and every panel.
- A path that only changes the selection calls `_sdSelectRender()`, which runs `renderSelectionOnly()` (selection marks and panels). The canvas DOM stays, so running CSS animations, playing videos and the state the design's scripts gave the page carry on, and a large page is not rebuilt on every click.
- Exception: selecting an element of a login region session state the canvas is not drawing switches states, which takes a `render()` (`_sdSelectionNeedsCanvas()`).
- Form controls are selected on `mousedown` through `renderSelectionOnly()`; a `renderCanvas()` there makes Chromium drop its synthetic `change` (designer-panel-element-identity.md).

## How does the overview keep up without a redraw?
- `renderSelectionOnly()` marks rows in place with `_sdTreeSelectInPlace()` when the list was drawn for the same tree and the same edit epoch (`list._sdTreeEpoch === _sdEditEpoch`); otherwise it calls `renderTree()`. Redrawing a thousand-row tree on every click cost about 200 ms.
- `_sdEditEpoch` moves in `saveState()` and `render()` (`_sdEditableInvalidate()`). A path that changes the tree without either must call `renderTree()` itself, or the overview keeps marking rows of the old tree.

## What does a scroll do?
- Only the viewport moves, not the page. The canvas scroll handler (document scroll only, once per frame) runs `_sdOnCanvasScroll()`: the selected and the hovered wrapper get their toolbar spot (`sd-tb-below` / `sd-tb-inside`, designer-canvas-chrome-placement.md) and chrome placement, and the size badge follows.
- The full pass `adjustCanvasToolbars()` (fidelity pass, every wrapper measured and re-classed) does not run on scroll. It used to run on every scroll event, the scrolls of inner boxes (a sliding carousel) included.

## Why is a wrapper's toolbar empty?
- `buildNodeEl()` leaves `.sd-tb` empty and hangs its builder on `tb._sdFill`; `_sdTbFill(w)` fills it the first time it is needed (hover, selection).
- A new state that shows the toolbar calls `_sdTbFill(wrap)` at that point; never assume the buttons exist in every wrapper. The box itself stays in place, so the wrapper's child order (which position selectors read) does not change.

## Why do canvas passes write only differences?
- A canvas style recalculation is expensive: thousands of hidden toolbars and the bridged selector rules (designer-canvas-selector-bridge.md). Passes measure everything first and write after (one layout), and write only what changed: a class or style that stays is never removed and put back.
- Measurement-bound fixes run in `_sdCanvasFidelityPass()` on the frame after a render (`adjustCanvasToolbars()`), and again through `_sdFxSoon()` when the canvas resizes or a stylesheet or picture arrives. Selection-bound chrome (size badge, the selected wrapper's toolbar) is placed again by `_sdPlaceSelectedChrome()`.

Related: docs/memory/designer/designer-canvas-design-assets.md, docs/memory/designer/designer-overview-follows-selection.md
