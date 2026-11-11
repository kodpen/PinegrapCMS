---
name: designer-bold-pages-steps-2
description: Steps B7 to B12 of the designer bold pages plan: pinned scene, looks and palettes, video, dark and light chapters, design colour mode, template colour classes
---

## Which steps come later?
- How the steps are cut and B1 to B6: [[designer-bold-pages-steps]].

- B7 (L, waits for B2): pinned scene with steps. A general block "Pinned Story" (group "Scroll Scenes"): a tall section with a sticky stage; a `scene` effect makes the script write the step number on the section from the scroll progress, and CSS shows that step's layer.
- B7: no script or reduced motion: the layers stand one under the other. Files as B2 plus the block; tests B2's and the block on the canvas.

- B8 (M, done #8692): new looks and palettes. Looks `display` (very light large display type, tight tracking, large corners, soft depth) and `precision` (flat, square, thin weights, wide spacing); palettes for product pages (near-black, silver, electric blue) written by `php tools/build_theme_palettes.php`.
- B8 files: `assets/css/themes/looks/`, `pg_design_looks()`, `pg_design_palettes()` in `includes/fn/design_themes.php`, language keys. Tests: the theme tests, contrast on light and dark.

- B9 (M, waits for B9a): a hero video that plays by itself, on B9a's Video item: muted and looping, with a pause button, not playing when the visitor prefers reduced motion. Decided by the owner on 2026-11-11 (`workflow/decisions-made-product-44.md`); the rule: [[designer-block-motion]] (WCAG 2.2.2).
- B9a (S, done #8744): a Video item in the palette (file manager picker, poster, controls, no autoplay), usable in B1's hero.

- B10 (M, waits for B2): dark and light chapters. A `chapter` effect on a section: when it reaches the window's middle, the page's background and text colour move to the section's own in a transition; reduced motion: a cut. Files and tests as B2.

- B11 (M, done #8711): a design-wide colour mode (light, dark, follow the system) in the Theme pane, written as `data-bs-theme` on `<html>`, a visitor's switcher still winning; empty is today's behaviour. Decided by the owner on 2026-11-11 (`workflow/decisions-made-product-44.md`); its new `style` column is approved in `workflow/decisions-made-schema-15.md`.

- B12 (M, waits for appstudio-1 and dealership-1 to land: same files): `bg-primary text-white` becomes `text-bg-primary` in the template files (39 folders), and a template test fails on the fixed colour classes of [[designer-block-theme-colours]].
