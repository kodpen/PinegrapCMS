---
name: designer-bold-pages-steps
description: Steps B1 to B6 of the designer bold pages plan: full-bleed and giant type blocks, scroll effects, count-up, variant picker, product bar, wide gallery slides
---

## How are the steps cut?
- About 1,000 product lines each, ordered by what the appstudio and dealership templates need first ([[designer-bold-pages]]); B7 to B12: [[designer-bold-pages-steps-2]].
- Effects keep the approved shape ([[designer-interactions]]): `props._fx`, `data-pg-fx-*`, `pg_fx.src.js` with CSS and observers, no library, reduced motion honoured, the twins `pg_fx_normalize()` / `_sdFxNormalize()` and `pg_fx_attrs()` / `_sdFxAttrs()`, rows in `propsInteractions()`, then `php tools/build_js.php`.
- New blocks go into a general group under a neutral name ([[design-template-own-look]]), built to [[palette-component-rules]].

## Which steps come first?
- B1 (M, done #8685): full-bleed blocks and giant type. `_SD_BLOCK_CSS` classes `pg-display-xl` / `pg-display-xxl` (`clamp()` up to about 9 rem, the look's display weight), `pg-bleed`, `pg-screen` (100svh); a general block "Hero: Full-Screen Media" (picture edge to edge, giant headline on `pg-shade-bottom`) in Marketing.
- B1 files: `style_designer.src.js` palette and builder. Test: `tests/designer_blocks_test.php`. Done: placed on the canvas and a page at 360/768/1280 px, contrast as [[designer-block-theme-surfaces]].

- B2 (M, free): scroll-linked effects. `_fx` effects `scroll-zoom` (scale from a start percent to 100 while the node crosses the window), `scroll-fade`, `scroll-rise`, each with a `from` field, played by the parallax loop once per frame; reduced motion or no script: the final state.
- B2 files: `includes/fn/designer_fx.php`, `assets/js/pg_fx.src.js`, the editor twins and panel. Tests: `tests/designer_fx_test.php` (normalizer, attributes, the script under Node).

- B3 (S, free): numbers that count. A `count` effect on text nodes, trigger view: the real number stays in the markup; the script counts from zero once seen, keeping separators, prefix and suffix; reduced motion: no counting. Files and tests as B2, no wait on it.

- B4 (M, free): a general variant picker. `launch-colours` becomes the general block "Variant Picker" (Marketing): colour dots or text pills, two to six choices, a picture each, radios and `:has()` as now; Launch places it, no copy left behind.
- B4 files: `style_designer_blocks_launch.js`, the core palette, the launch home builder. Tests: `tests/designer_block_families_test.php`, `tests/design_template_launch_assembly_test.php`.

- B5 (S, free): product bar. A general block "Product Bar": name, anchor links, one call-to-action button, `sticky` with an offset below a sticky site header, dark and light variants. Core palette and builder; test `tests/designer_blocks_test.php`.

- B6 (S, free): wide gallery slides. `scroller-cards` gets an option "Wide slides" (one large picture per slide, 80 to 90 percent of the window, snap centre): an option, not a new block. Builder and options panel; test `tests/designer_blocks_test.php`.
