---
name: design-template-own-looks
description: Plan (owner 2026-11-07): block families in their own source files (B0), Launch as an Apple-like product page (L1-L3), then a polishing round for every template
---

## What is it?
- The rule `designer/design-template-own-look.md` (decision: `workflow/decisions-made-product-37.md`) applied to every template: its sector sections become a palette block family, its home and signature pages are rebuilt on them, with motion where it fits, version raised. Order of the rest and the open template plans: [[design-template-own-looks-order]].
- Claim as `[plan:design-template-own-looks] <step> — <topic>`; about 1,000 product lines each; memory in `designer/` (one file per family, `designer-blocks-<family>.md`).

## B0 (S): how does a family stay out of style_designer.src.js?
- Done (#8022): `designer/designer-block-family-files.md`. A family lives in `assets/js/style_designer_blocks_<family>.js`, bundled after the core into `style_designer.min.js` by a sorted glob in `pg_js_bundles()` (so a family PR never edits `output.php`). It registers its prefix, palette group, palette items, builders, `_SD_BLOCK_CSS` rules and `_SD_BLOCK_CSS_NEEDS` through one registration call the core reads in `_sdBlockPaletteItems()`, `_sdBlockPaletteGroups()`, `createFromPalette()` and the block stylesheet; general blocks (G1, G2 in the -order file) live in such files too, under an existing group's prefix.
- The readers of the core file take the family files too: `_pg_designer_block_css_source()` (a template's block CSS), `pg_designer_i18n_keys()` (`_sdT` keys; its cache stamp covers every file), `tools/check_lang.php`, `tests/designer_blocks_test.php`, the bs6 tools (family files are site output, Bootstrap 5.3.8). The existing families stay where they are. Test: a sample family file registers and draws.

## How does it fit beside designer-performance-and-publish?
- D1-D5 of that plan edit `style_designer.src.js` one worker at a time; B0 does too, so it takes one turn in that line: right after D1 (#7987) merges, before D2 is claimed; if D2 is already claimed, after D2's merge and before D3. That plan's file is held by #7987, so the order is written here; its coordinator reads this section.
- After B0, family steps touch only their own family file, their template folder, the language shards and tests: they run beside D2-D5. A hook a family needs in the core is a small step of its own in the D line's next turn.
- D5 (parts) keeps the family files bundled after the part that defines the registration call, or loads them with the palette through its loader; its worker reads this.

## Launch: what makes it an Apple-like product page?
- L1 (M) Done (#8052): `designer/designer-blocks-launch.md`; the feature tile grid reuses `bento-features`. Family `launch-`, group "Product Launch". After B0. Blocks: the product hero (large phone picture on a dark band, name, price line, two buttons; entrance on load), the sticky showcase (picture column `_fx` sticky beside feature panels revealed on view), the feature tile grid (revealed with stagger), the camera band (full-width picture with parallax), the colour band (finish swatches over the picture), the spec comparison (models side by side, a sticky head row, tabular figures), the in-the-box strip, the buy band. Reduced motion: the panels show at once, the sticky column stays put.
- L2 (M) Done (#PRNUM): `designer/design-template-launch.md`. After L1. Home and `features` rebuilt on the family: the sections follow the product, not the story band, figures, testimonials and FAQ sequence; `plx-` rules the family covers dropped.
- L3 (M) After L2. `camera`, `specs`, `compare` rebuilt (the comparison from `$models`, one source as today), version 2.0.0, the launch tests updated, `designer/design-template-launch.md` rewritten.

## Where does it start?
- B0 in its turn after #7987. L1 the moment B0 lands; the polishing round starts when L3 lands ([[design-template-own-looks-order]]); Skiresort's step 3 waits for its family step there.
