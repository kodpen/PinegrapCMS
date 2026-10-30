---
name: system-status-gauge-layers
description: System Status gauge (widget 2) as five stacked SVGs: paint order, which layer animates, will-change, the off-screen pause and its redraw guard, reduced motion
---

## How is the gauge drawn?
- `includes/dashboard/widgets/widget_2.php` prints five SVGs inside `.pg-health-dial`, all `viewBox="8 8 124 72"`, in paint order:
  1. `.pg-health-layer-base`: the groove plus the shared `<defs>` (both gradients, the three `feGaussianBlur` filters);
  2. `.pg-health-layer-bloom`: the bloom circle;
  3. `.pg-health-layer-glow`: the glow circle;
  4. `.pg-health-layer-halo`: the static `g.pg-health-cell-glow`;
  5. `svg.pg-health-gauge` (`role="img"`, the "Overall System Health {var:1}%" label): `g.pg-health-cells` with its clipPath defs and the `.pg-health-inner` hairline.
- Layers 1-4 carry `pg-health-layer`, `aria-hidden="true"`, `focusable="false"` and sit `position:absolute; inset:0` with `overflow:visible`. The top SVG stays in the flow (it gives the dial its height; the readout keeps `top:80%`) and is `position:relative` so it paints over them.
- Ids are unchanged: `url(#id)` resolves across inline SVGs of one document.

## Why five SVGs instead of one?
- A filter in the same SVG as animating content is redrawn with it every frame. With the three blurs and 235 twinkling rects in one SVG the dashboard kept the main thread busy about 207 ms per second (#3592). Now only the top SVG, which holds no filter, repaints as the cells twinkle; it has `will-change: transform`, so its repaint never invalidates the layers under it.

## What animates where?
- Cells: `pg-health-cell` on `.pg-health-cells > rect`, unchanged.
- Glow and bloom breathe on their layer `<svg>` (`will-change: opacity`), never on the filtered circle, which sits at opacity 1. Rest opacity on the layer: glow .62, bloom .4; light theme: bloom layer `display:none`, glow rests at .16. Keyframes have literal values per layer and theme: `pg-health-glow-breathe` (.52↔.72, 5.1s, -2.4s), `pg-health-bloom-breathe` (.31↔.5, 7.1s, -6.1s), `pg-health-glow-breathe-light` (.11↔.21).
- The halo never animates.
- Every infinite gauge animation stays inside `@media (prefers-reduced-motion: no-preference)`; reduced motion sees the rest values, as before.

## When does it pause?
- A small inline script at the end of the widget's `$output_data` (it runs on both draw paths: `pg_widget_apply` in `welcome.php` and `window.pgHealthReload` in `backend.src.js`) watches every `.pg-health-dial` with one `IntersectionObserver` kept on `window.pgHealthObserver`; a redraw disconnects the old one first, so no second observer piles up. Off screen it adds `.is-paused`, which sets `animation-play-state: paused` on the cells and the layers. Without `IntersectionObserver` nothing pauses. A hidden tab gets no animation frames anyway.

## What pins it?
- `tests/dashboard_gauge_motion_test.php`. Same PR also pauses the loading curtain's loops once done (`loading-curtain.md`). Added in #3676.
