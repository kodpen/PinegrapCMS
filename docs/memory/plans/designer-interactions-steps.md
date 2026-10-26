---
name: designer-interactions-steps
description: Plan (designer session): interaction builder for designer nodes cut into steps I1–I4: props._fx model, data-pg-fx-* attributes, pg_fx script, Interactions tab, canvas preview
---

## Where does it come from?
- The interactions half of `plans/designer-interactions-collections.md` (approved 2026-10-19): CSS transitions and IntersectionObserver only, no GSAP, `prefers-reduced-motion` honoured, no per-component JavaScript.

## How is it cut?
- One interaction per node in `props._fx` (next to `_attrs`): `effect` (fade, slide-up/-down/-left/-right, scale, sticky, parallax), `trigger` (load, view, hover, click; none for sticky and parallax), `duration`, `delay` (ms), `easing` (ease, ease-in, ease-out, ease-in-out, linear), `stagger` (ms, load/view: the children move one after another instead of the node), `repeat` (view: again on every entry), `distance` (px), `offset` (sticky top, px), `speed` (parallax, percent). `pg_fx_normalize()` and its JS twin clamp and drop the rest; the page writes `data-pg-fx="<effect>"`, `data-pg-fx-trigger` and only the non-default values.
- I1 — data model and front end: normalizer and attribute writer (PHP + editor twin) on content, semantic and grid nodes; `assets/js/pg_fx.src.js` (+ `.min.js` bundle); `pg_fx_inject()` (a head style that hides entrance targets until the script arms them, with a 3 s fail-safe, plus the script) in `get_page_content.php`; the Preview row; tests. No panel yet.
- I2 — Interactions tab: per-node controls for every field, undo, translations, copy/paste keeps `_fx`; the code view gives `data-pg-fx-*` back to `_fx` (like `_sdCodeSetTitleAttr()`).
- I3 — canvas preview: the canvas loads the script and binds again after every render (`window.pgFxBind`), follows the Animations pause (paused: final state, nothing hidden) and the drag hold; a "Play" button replays the selected node's interaction.
- I4 — beyond plain nodes: palette components, system widget nodes and shared components carry `_fx` too (their own markup paths).
- Each step updates `designer/designer-interactions.md`.

## What remains?
- I1 done (#1922); then I2, I3, I4 in order; each waits for the previous merge.

## Where to start?
- `includes/fn/designer_fx.php`, `assets/js/pg_fx.src.js`, `_sdFxAttrs()` beside `_sdNodeExtraAttrs()` in `assets/js/style_designer.js`, `tests/designer_fx_test.php`.
