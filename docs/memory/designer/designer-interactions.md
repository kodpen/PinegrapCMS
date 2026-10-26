---
name: designer-interactions
description: Interactions of designer nodes: props._fx, the data-pg-fx-* attributes, pg_fx.src.js (entrance, hover, click, sticky, parallax), the head style and fail-safe, reduced motion, the twins and tests.
---

## Why this shape?
- Approved 2026-10-19 (`plans/designer-interactions-collections.md`): data attributes read by one small script, CSS transitions and IntersectionObserver only, no GSAP, `prefers-reduced-motion` honoured, no per-component JavaScript. Built in I1 #1922, I2 #1961, I3 #1977, I4 #1998.

## Where is a node's interaction kept?
- In `props._fx` (next to `_attrs`): one effect per node. `pg_fx_normalize()` (`includes/fn/designer_fx.php`) and its twin `_sdFxNormalize()` keep the known fields in range and drop those that do not apply (no trigger or timing for sticky and parallax, `stagger` only for load/view, `repeat` only for view, no `distance` for fade). An unknown effect is no interaction.

## What does the page write?
- `pg_fx_attrs()` / `_sdFxAttrs()`: `data-pg-fx="<effect>"`, `data-pg-fx-trigger`, then only values that differ from the defaults (duration 600, delay 0, easing ease-out, stagger 0, distance 0 = the script's own, offset 0, speed 20), then `data-pg-fx-repeat`. Written by `_render_extra_attrs()` after the title on content, semantic and grid nodes (`$title` true) and on components (`$fx` true, I4): every `_render_component_html()` case but the language switcher (a placeholder drawn later) puts it on the element that takes the component's id, its outer one. The editor's HTML puts it in the component's first tag (`_sdFxIntoFirstTag()` in `_sdEmitNode()`); the canvas through `_sdApplyNodeAttrs()`, as for every node. A `data-pg-fx*` entry typed into the Attributes panel is left out on a node that has an interaction.
- Nodes inside a system widget or a shared component are drawn by the same paths and keep their attributes through the finish pass; `pg_fx_inject()` runs after both are expanded. A shared component's or widget's placement (`shared_ref`, a marker) has no element and no interaction.

## Where does the operator set it?
- In the Interactions section of the properties panel: `designer/designer-interactions-panel.md`.

## How does the page get the script?
- `pg_fx_inject()` in `get_page_content.php` (not in an e-mail), once everything is drawn in, for a page matching `pg_fx_pattern()`: a `<style id="pg-fx-css">` before `</head>` and the `pg_fx.min.js` bundle (`pg_js_bundle_urls()`, sources in src mode) before the last `</body>`. The Preview's `_SD_PAGE_SCRIPTS` row uses the same expression.
- The head style hides load/view nodes until the script adds `pg-fx-armed`, only under `prefers-reduced-motion: no-preference` and `scripting: enabled`; a 3 s `pg-fx-failsafe` animation shows them if the script never runs. It also makes sticky nodes `position: sticky`.

## What does pg_fx.src.js do?
- Entrance, hover, click, sticky and parallax on the page: `designer/designer-interactions-script.md`.

## How is it tested?
- `tests/designer_fx_test.php`: normalizer, attributes, render on nodes and components, nodes inside a widget, inject, Preview expression, editor twins and the script's reading of the page under Node; the panel under Node; the twin cases also in `tests/designer_node_attrs_test.php`, the code view in `tests/designer_code_view_attrs_test.php`.

Related: docs/memory/designer/designer-interactions-panel.md, docs/memory/designer/designer-block-motion.md
