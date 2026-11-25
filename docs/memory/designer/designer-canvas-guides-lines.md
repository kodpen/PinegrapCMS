---
name: designer-canvas-guides-lines
description: Layout lines (Shift+G) and the "Canvas guides" switch of the View menu (CG5 #11623, part guides): what they draw, what the switch turns off, where kept, the key, the numbers
---

## What do layout lines draw?
- Shift+G on the editor or the canvas draws the page's container (two dashed accent edges), its twelve columns' content (accent tint) and the gutters between them over the whole canvas height, until Shift+G again or Escape. Nothing selected is needed.
- The container is the first `.container` / `.container-{sm…xxl}` on the canvas with a width, with its own paddings and `--bs-gutter-x`; a page without one gets Bootstrap 5.3's container for the canvas width (540, 720, 960, 1140, 1320; full width below 576), centred, gutter 1.5rem.
- They follow the bar's screen size, zoom and Fit: redrawn after a canvas draw (`_sdGuidesSync()`), after `_sdPlaceSelectedChrome()` (zoom, fidelity pass) and on the canvas' scroll and resize.
- Not offered in Prototype, in a text field, or while "Canvas guides" is off. Escape inside a text being typed or a dialog is left alone.

## What does "Canvas guides" do?
- A switch row of the canvas bar's View menu (`#sd-vb-guides`), on by default; off is kept per browser in `localStorage` `pinegrap_designer_canvas_guides` = `0` (`_sdGuidesOff()`, `_sdGuidesSet()`).
- Off: no spacing bands or column handles (`_sdBandsDraw()`), Alt does not measure (`_sdGuidesOne()`), the layout lines go and Shift+G does nothing. The badge counts it while on.
- Under it two plain lines, not buttons, name the keys: "Measure: point at another element" Alt, "Layout lines" Shift+G; dimmed while the switch is off (CSS sibling rule). The Shortcuts window lists both; the command palette has "Toggle Canvas guides" and "Toggle Layout lines".

## How is it built?
- Pure: `PGCanvasGuides.containerWidth(width)` and `layout(page, opts)` in `pg_canvas_guides.js` (tests in `tests/designer_canvas_guides_test.php`).
- Part `guides` (`style_designer_guides.js`): `lines(host)` / `linesOff()`, layer `.sd-gl-layer` in `.sd-canvas-wrapper` (z-index 20, under Measure's), never in the canvas page. Listeners (Escape, both windows' resize, the canvas scroll) only while the lines show.
- Core section `// ── Canvas guides: the switch and the layout lines` in `style_designer.src.js`; the key through `_sdLinesKey(e)` in the canvas and editor key handlers. Tests: `tests/designer_guides_part_test.php`, `tests/designer_canvas_bar_test.php`.

## What was measured?
- See the PR #11623 body for bundle sizes. No browser run: nothing is drawn while the pointer moves; while the lines are off no listener or timer is added (one `localStorage` read per bands draw and per Alt check).
