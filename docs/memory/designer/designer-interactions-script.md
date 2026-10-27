---
name: designer-interactions-script
description: What pg_fx.src.js does with the data-pg-fx-* attributes: entrance start states and the observer, stagger, hover and click, parallax, the node's own styles, reduced motion, pgFxBind.
---

## What does pg_fx.src.js do?
- load/view: start state (opacity 0, moved by the distance — 24 px — or scale .9), then a CSS transition to the node's own state; view waits for IntersectionObserver, `repeat` re-arms on exit. The observer has threshold 0 and no margin, and without a stagger a slide stays below the node's own width (left/right) or height (up/down): an armed node is past the fail-safe, and with a -8% bottom margin and threshold 0.1 a node in the page's last strip, one taller than ~9 windows, or one slid off the screen (the observer measures the moved box) was never seen and stayed at opacity 0 (#1922); `stagger` moves the children (a `display: contents` child stands for its first boxed child). hover/click: towards the effect (fade .6, lift 6 px, scale 1.05), click toggles `pg-fx-on`. Parallax: speed percent of the distance from the window's middle, on scroll, once per frame.
- The node's own opacity, transform and transition are kept (`pgFxBase`) and composed with. Reduced motion: nothing moves; switched on while open, every node is put back. `window.pgFxBind(root)` binds unbound nodes; `window.pgFx` holds the pure helpers.

## How is it tested?
- `test_designer_fx_script_reads_the_page` and `test_designer_fx_script_on_the_canvas` in `tests/designer_fx_test.php`, under Node.

Related: docs/memory/designer/designer-interactions.md
