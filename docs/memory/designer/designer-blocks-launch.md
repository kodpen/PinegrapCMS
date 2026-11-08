---
name: designer-blocks-launch
description: The launch- block family (Product Launch): seven blocks, their interactions, the CSS colour switch, bento-features reused, the sticky spec head
---

## What does the family hold?
- `pinegrap/assets/js/style_designer_blocks_launch.js`, the first family in its own file ([[designer-block-family-files]]), group "Product Launch" (`bi-phone`), added in #8052 for the Launch template's rebuild (`plans/design-template-own-looks.md`, L1).
- `launch-hero` (dark band, text `slide-up` on load with stagger, phone `scale` on load over `pg-launch-glow`), `launch-showcase` (picture column `sticky` offset 96 beside `pg-launch-panel` panels revealed on view), `launch-camera` (`parallax` band, photo in the style attribute as on Hero: Image Background, text on `pg-shade-bottom`), `launch-colours`, `launch-specs`, `launch-box`, `launch-buy` (cards revealed on view, stagger 150).

## Which existing blocks does it reuse?
- The feature tile grid is `bento-features` (Creative): same structure, so a page places it with a view interaction and stagger on its row instead of a copy.
- `launch-specs` is not `compare-table`: that one is a check/cross matrix; this one has model heads with prices, `scope="rowgroup"` groups, tabular figures and a sticky head row. Making it an option of the core block needs a core hook (its own step).

## How does the colour switch work without script?
- Radio `btn-check` inputs (`pg-launch-pick-<n>`) with labels holding a dot (`pg-launch-dot-<n>`) and the name, in a fieldset with a hidden legend; the pictures (`pg-launch-shot-<n>`) stack in one grid cell. `.pg-launch-colours:has(.pg-launch-pick-1:checked) .pg-launch-shot-1` shows the picture; `:has()` and descendant selectors only, so the canvas (`.sd-wrap` around every node) matches too.
- The dot colours are hex: the phone's finishes, fixed under any palette (as the template's `plx-swatch`). No transition under reduced motion.

## Why is the spec table's head only sticky from 992 px?
- A sticky cell sticks inside its nearest scrolling box: under `overflow-x: auto` (needed on phones) it never meets the window. From 992 px the three models fit, `pg-launch-specs` drops the overflow and makes `thead th` sticky with the body colour behind it.

## How is it checked?
- `tests/designer_blocks_test.php` (family files: builders, rules, classes), `tests/designer_block_families_test.php` (every shipped block draws under Node, its interactions survive `pg_fx_normalize()` unchanged), `tests/design_template_assets_test.php` (rules read as written).

Related: docs/memory/designer/designer-interactions.md, docs/memory/designer/design-template-launch.md
