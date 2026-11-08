---
name: design-template-launch-home
description: Launch template's home and features pages on the Product Launch blocks: builders, sections, motion, the colour picker's swatch classes, what left
---

## How are home and features built?
- On the Product Launch blocks ([[designer-blocks-launch]], L2 of `plans/design-template-own-looks.md`): `pages_public.php` builds them with the same structure and classes (`$launch_hero`, `$launch_camera`, `$launch_colours`, `$launch_showcase`, `$launch_box`; camera band and showcase take their content as parameters, shared with the camera page), their rules shipped with `pg_design_template_user_styles(array('launch-hero', …), $launch_css)`, motion as `props._fx` (`$fx`).
- Home: hero (load), highlight tiles (view, stagger), camera band (parallax), colour picker, model cards (view, stagger), trade-in, newsroom, launch band; the press quotes left the page. Features: page header, sticky showcase (a panel per highlight, still `#feature-<key>` search sections), colour picker with each finish's sentence (`#colours`), in the box.
- The colour dots are `pg-launch-dot plx-swatch-<key>`: the block draws the dot, the template's swatch class its finish; radios `lx-colour-<key>`, the first checked. `plx-swatch-lg` was dropped.

## How is it tested?
- `test_launch_assembly_home_and_features_on_launch_blocks` (`tests/design_template_launch_assembly_test.php`): every `pg-` class's rule ships in User Styles, interactions kept by `pg_fx_normalize()`, the motion in place, a labelled radio per finish with one checked, no press quotes.

Related: docs/memory/designer/design-template-launch.md, docs/memory/designer/designer-blocks-launch.md
