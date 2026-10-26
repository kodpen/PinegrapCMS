---
name: designer-interactions
description: Interactions of designer nodes: props._fx, the data-pg-fx-* attributes, pg_fx.src.js (entrance, hover, click, sticky, parallax), the head style and fail-safe, reduced motion, the twins and tests.
---

## Where is a node's interaction kept?
- In `props._fx` (next to `_attrs`): one effect per node. `pg_fx_normalize()` (`includes/fn/designer_fx.php`) and its twin `_sdFxNormalize()` keep the known fields in range and drop those that do not apply (no trigger or timing for sticky and parallax, `stagger` only for load/view, `repeat` only for view, no `distance` for fade). An unknown effect is no interaction.

## What does the page write?
- `pg_fx_attrs()` / `_sdFxAttrs()`: `data-pg-fx="<effect>"`, `data-pg-fx-trigger`, then only values that differ from the defaults (duration 600, delay 0, easing ease-out, stagger 0, distance 0 = the script's own, offset 0, speed 20), then `data-pg-fx-repeat`. Written by `_render_extra_attrs(..., true)` after the title, so on content, semantic and grid nodes only; a `data-pg-fx*` entry typed into the Attributes panel is left out on a node that has an interaction.

## Where does the operator set it?
- In the Interactions section of the properties panel: `designer/designer-interactions-panel.md`.

## How does the page get the script?
- `pg_fx_inject()` in `get_page_content.php` (not in an e-mail), once everything is drawn in, for a page matching `pg_fx_pattern()`: a `<style id="pg-fx-css">` before `</head>` and the `pg_fx.min.js` bundle (`pg_js_bundle_urls()`, sources in src mode) before the last `</body>`. The Preview's `_SD_PAGE_SCRIPTS` row uses the same expression.
- The head style hides load/view nodes until the script adds `pg-fx-armed`, only under `prefers-reduced-motion: no-preference` and `scripting: enabled`; a 3 s `pg-fx-failsafe` animation shows them if the script never runs. It also makes sticky nodes `position: sticky`.

## What does pg_fx.src.js do?
- load/view: start state (opacity 0, moved by the distance — 24 px — or scale .9), then a CSS transition to the node's own state; view waits for IntersectionObserver, `repeat` re-arms on exit. The observer has threshold 0 and no margin, and without a stagger a slide stays below the node's own width (left/right) or height (up/down): an armed node is past the fail-safe, and with a -8% bottom margin and threshold 0.1 a node in the page's last strip, one taller than ~9 windows, or one slid off the screen (the observer measures the moved box) was never seen and stayed at opacity 0 (#1922); `stagger` moves the children (a `display: contents` child stands for its first boxed child). hover/click: towards the effect (fade .6, lift 6 px, scale 1.05), click toggles `pg-fx-on`. Parallax: speed percent of the distance from the window's middle, on scroll, once per frame.
- The node's own opacity, transform and transition are kept (`pgFxBase`) and composed with. Reduced motion: nothing moves; switched on while open, every node is put back. `window.pgFxBind(root)` binds unbound nodes; `window.pgFx` holds the pure helpers.

## How is it tested?
- `tests/designer_fx_test.php`: normalizer, attributes, render on nodes, inject, Preview expression, editor twins and the script's reading of the page under Node; the panel under Node; the twin cases also in `tests/designer_node_attrs_test.php`, the code view in `tests/designer_code_view_attrs_test.php`.

Related: docs/memory/plans/designer-interactions-steps.md, docs/memory/designer/designer-block-motion.md
