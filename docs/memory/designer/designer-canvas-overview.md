---
name: designer-canvas-overview
description: Start file for the designer-canvas-* files: the canvas, its width buttons, Dark, Animations, context bar and element toolbar, the rules that bind it, which file holds what
---

## What is the canvas?
- The visual designer's middle pane: an iframe on the site's origin where the page is drawn from the tree, each node in an editor wrapper (`.sd-wrap`) with its own toolbar. `renderCanvas()` builds it, `applyAssetsToIframe()` brings the design's scripts and stylesheets, all in `assets/js/style_designer.src.js` (served as `.min.js`).
- It must look and behave like the published page while every element stays selectable; the editor's spacing is the only difference kept on purpose.

## Which rules bind it?
- No owner decision names the canvas; the key rules of `README.md` do: site output, the canvas included, stays on Bootstrap 5.3.8 (`ui/bootstrap-6.md`); a one-node edit redraws with `_sdRenderNode` (`designer-incremental-canvas.md`); panels draw on demand (`designer-panels-on-demand.md`); an element is found with `_sdFindNodeForEl` (`designer-find-node-for-el.md`).
- The canvas opens in the design's colour mode (`design-colour-mode.md`).
- Bootstrap's direct-child rules on the canvas: `bootstrap-direct-child-canvas.md`.

## What are its parts, by the label on screen?
- Width buttons ("768px · MD") and the resize handle: the canvas gets exactly that width; Bootstrap rules rebuilt across the wrappers, `sd-fx-fit`, what differs on purpose: `designer-canvas-page-parity.md`.
- Dark (`#sd-vb-theme`) and the Themes tab's mode tabs, with any theme switcher on the page, as one state on the canvas `<html>`, never stored: `designer-canvas-theme-view.md`.
- Animations (`#sd-vb-motion`): the design's motion paused or played; the design's scripts run once per canvas document: `designer-canvas-design-assets.md`.
- The context bar (`#sd-ctx-bar`: Dropdown, Navbar, Nav, Accordion, Modal actions), the element toolbar above, below or inside, the page root's name tag, the size badge: `designer-canvas-chrome-placement.md`.
- Buttons, toggles and scrollers that work on the canvas as on the page (`_SD_CANVAS_CONTROLS`): `designer-canvas-controls.md`. Links never navigate; the editor opens dropdowns itself: `designer-canvas-links.md`.
- An open modal, offcanvas or dropdown menu is canvas state only: `designer-canvas-open-state.md`.

## Which file holds the machinery?
- Which call redraws what (`render()`, `_sdSelectRender()`, the scroll pass, toolbars filled on first need, writes by difference): `designer-canvas-redraw-paths.md`.
- The two wrapper models (boxed Bootstrap, see-through for another framework), `IFRAME_CSS`, `_sdCanvasFidelityPass()`, the express order frame: `designer-canvas-style-designer.md`.
- A design on its own framework: its child and sibling selectors bridged across the wrappers (`_sdBridgeSheets()`): `designer-canvas-selector-bridge.md`.
- Absolutely positioned elements (`sd-fx-abs`, `sd-fx-unpos`) and their wrappers' chrome: `designer-canvas-absolute-position.md`.

## How is a new canvas behaviour added?
1. A control bound by its own script joins `_SD_CANVAS_CONTROLS`; a new Bootstrap component is checked for a sibling or child lookup (`designer-canvas-links.md`).
2. A path that changes the tree calls `render()` (or `_sdRenderNode()` for one node); a selection-only path `_sdSelectRender()`; a state that shows a toolbar calls `_sdTbFill()`.
3. A measured fix goes into `_sdCanvasFidelityPass()`: measure all, then write only what changed; a canvas-body class of the editor joins `_EDITOR_STATE_CLASSES`; script reaches the canvas only through `applyAssetsToIframe()`.
4. New editor markup that draws the canvas carries `/* @pg-site-output */` (`ui/bootstrap-6-editor-markers.md`); `php tools/build_js.php`; a test beside the cluster's (`tests/designer_canvas_*_test.php`); the topic file, then this one.
