---
name: designer-block-family-files
description: Block families in their own files (style_designer_blocks_<family>.js): the registration call, the kit, how the palette, drop, block rules and PHP readers take them, the checks (B0)
---

## Where does a new block family live?
- In `pinegrap/assets/js/style_designer_blocks_<family>.js` (lower case, digits, `_`), never in `style_designer.src.js`. `pg_designer_js_sources()` (`includes/fn/output.php`) lists the core first, then the family files sorted by name; `pg_js_bundles()` builds `style_designer.min.js` from `pg_google_fonts.js` plus that list, so adding a family edits neither the core nor `output.php`. Run `php tools/build_js.php` after editing one.
- The families shipped before (news, creative, local business…) stay in the core.

## What does a family file contain?
- One call: `StyleDesigner.registerBlockFamily(function (kit) { var _sdT = kit._sdT; … return { prefix, group, items, build, css, needs }; });` (sample: `tests/fixtures/designer/style_designer_blocks_sample.js`).
- `prefix` is the `componentType` prefix (`launch-`); `group: { title: _sdT('…'), icon }` gives it a palette group, placed after the core groups; a general family (G1, G2) leaves `group` out and uses a core group's prefix (`features-`). `items` have the shape of `_sdBlockPaletteItems()`, each closed by a `desc: _sdT('…')` line: the block's one-sentence description in the hover card ([[palette-block-info]]); `build(ctype)` returns the tree or `null`.
- `css` and `needs` are `var _SD_BLOCK_CSS = {` / `var _SD_BLOCK_CSS_NEEDS = {` declared at four spaces inside the factory and closed by `\n    };`: the PHP reader parses that text.
- `kit` holds `_sdT`, `createNode` and the block builders the core's block branch uses (`uid div tag section row col h p icon btn link time region iconLink img intro`, `_sdBlockKit()`); the branch takes them as `_bkDiv`, `_bkIntro`… Keys stay single-quoted `_sdT('…')` literals.

## How does the core read the families?
- `_sdBlockFamilies()` runs every factory once, on first use (palette, drop): items join `_sdBlockPaletteItems()`, groups `_sdBlockPaletteGroups()`, rules `_SD_BLOCK_CSS` and `_SD_BLOCK_CSS_NEEDS`. A core rule or a type an earlier family lists is never replaced; a factory that throws or returns no `prefix`/`build` is skipped.
- `createFromPalette()` asks `_sdBlockFamilyOf(ctype)` before the core builders (after the rules are ensured); `_sdWrapForParent()` does not wrap a family block dropped on the page root.
- Node tests that cut `_sdWrapForParent()` stub `_sdBlockFamilyOf()`.

## Which PHP and tools read the family files?
- `pg_designer_i18n_keys()` (its cache stamp covers every file's name, mtime and size), `_pg_designer_block_css_source()` (core first, `_pg_designer_block_css_parse()` per file), `tools/check_lang.php` (`_sdT` keys), `bs6_scope_kind()` (site output, Bootstrap 5.3.8).
- `tests/designer_blocks_test.php` checks every family file and the sample (registration, prefix, a builder per type, no core type, rules naming their key, no `!important`, no core rule redefined, every `pg-*` class needed); `tests/designer_block_families_test.php` runs the core's family code with the sample under Node.

Related: docs/memory/designer/designer-palette-tabs-and-blocks.md, docs/memory/core/js-bundles.md, docs/memory/plans/design-template-own-looks.md
