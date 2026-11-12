---
name: designer-interactions-script
description: What pg_fx.src.js does: entrance start states and the observer, stagger, hover and click, parallax, scroll-linked effects, the node's own styles, reduced motion, pgFxBind.
---

## What does pg_fx.src.js do?
- load/view: start state (opacity 0, moved by the distance — 24 px — or scale .9), then a CSS transition to the node's own state; view waits for IntersectionObserver, `repeat` re-arms on exit. The observer has threshold 0 and no margin, and without a stagger a slide stays below the node's own width (left/right) or height (up/down): an armed node is past the fail-safe, and with a -8% bottom margin and threshold 0.1 a node in the page's last strip, one taller than ~9 windows, or one slid off the screen (the observer measures the moved box) was never seen and stayed at opacity 0 (#1922); `stagger` moves the children (a `display: contents` child stands for its first boxed child). hover/click: towards the effect (fade .6, lift 6 px, scale 1.05), click toggles `pg-fx-on`. Parallax: speed percent of the distance from the window's middle, on scroll, once per frame.
- The node's own opacity, transform and transition are kept (`pgFxBase`) and composed with. Reduced motion: nothing moves; switched on while open, every node is put back. `window.pgFxBind(root)` binds unbound nodes; `window.pgFx` holds the pure helpers.

## How do the scroll-linked effects play?
- scroll-zoom / scroll-fade / scroll-rise (B2 #8696) run in the parallax loop `float()`, once per frame, on scroll, resize and `load`; it measures every box before writing any. Progress (`pgFx.progress()`) is 0 while the node's top is below the window and 1 once its middle reaches the window's middle; then the node keeps its own state.
- The end is never below the node's top with the page scrolled to its end (`floor` = top less the scroll left), so a node in the page's last strip finishes at the page's end instead of staying half played (the #1922 trap); one that never comes into the window counts as whole.
- The box is measured without the scale or rise it already has (layout height, middle less the rise). A fade is a part of the node's own opacity. At its own state the node gets its own transition back, so a hover effect still eases. Reduced motion: the node shows its own state; switched on while open, it is put back.
- A rise never makes the page longer (#9018). A browser counts a transformed box into the page's height, so a rise near the page's end pushed the end down by up to its distance, then the end moved back while the visitor scrolled to it. The rise starts from the room under the node at most, less 1 px (`pgFx.riseFrom()`); a risen node reaching the page's end is put back before the page is measured, so a page that grew shorter is not held at its old height.

## How is it tested?
- `test_designer_fx_script_reads_the_page`, `test_designer_fx_script_on_the_canvas`, `test_designer_fx_script_scroll_effects` and `test_designer_fx_script_rise_keeps_the_page_length` in `tests/designer_fx_test.php`, under Node, on a fake page (`_test_fx_fake_dom()`).
- Checked in Chromium for #9018 (360, 768 and 1280 px): each scroll-linked node at every 37 px against the rule, no drift over passes, a taller window, reduced motion on and off while open, scripts off. 120 nodes on a long page with the CPU slowed four times: about 2 ms of script a frame, no dropped frame. Firefox, Safari and a real phone not checked.
- A utility class with `!important` (`opacity-50`) wins over Fade on scroll: the node keeps that opacity throughout.

Related: docs/memory/designer/designer-interactions.md
