---
name: palette-block-info
description: Blocks hover card: the one-line description of every ready-made block (core in _sdBlockInfo, family in its item's desc), how a line is written, the test that holds a new block to it
---

## Where does a block's description line come from?
- From `_sdBlockInfo()` in `style_designer.src.js` (right after `_sdPalInfo()`): a map from `componentType` to one `_sdT()` line, built on first use. `_sdBlockDesc(item)` reads it for a palette row; `_sdPalTipHTML()` shows it under the block's drawing ([[palette-block-sketch]]).
- A description `d` in `_sdPalInfo()` wins over it; the Components rows keep theirs.
- It covers the core list of `_sdBlockPaletteItems()` only (127 blocks when it was added, #8758).
- A family block's line is the `desc` of its item in the family file ([[designer-block-family-files]]): `_sdBlockFamilies()` keeps each item's `desc` by `componentType` and `_sdBlockDesc()` reads it when the core map has no line (#8777).
- Every family block has its line: A to H in #8777 (56 blocks), `florist` and `launch` to `zoo` in #8797 (55 blocks), 111 in 37 family files then.

## How is a line written?
- One sentence of at most 140 characters, ending in a full stop, no ". " inside: what the block adds, read from the tree its builder returns (the parts in order, columns, the band's colour), not its label again.
- Name parts by what a visitor sees ("a dark band", "three cards with an icon"), not by class names; say "in the main colour of the design" for `text-bg-primary`.
- No two blocks share a line: say what sets a look apart from its neighbours (centred, split, with a form, on a picture).
- Turkish only through `php tools/tr_add.php` (a `--from=<json>` file for many lines at once).

## What holds a new block to it?
- `tests/designer_block_info_test.php` reads the core list and the map from the source: it fails for a core block without a line, a line for no core block, a line that is not one `'type': _sdT('text')` literal, a line written twice, and a line that is not one short sentence.
- It reads every family file's `items` too: a family block without a `desc` fails, unless its family is on `designer_block_info_test_undescribed_families()` (empty since #8797); a family on that list with a line for every block fails until it is taken off. Every `desc` is one `desc: _sdT('text')` literal closing its item, one short sentence, never the same as a core or another family line.
- `tests/designer_block_sketch_test.php` checks under Node that the hover card of a block shows its line, a family block's from its `desc`.
- A PR that adds a core block or a block to a described family writes its line in the same PR; while two PRs race, the later one writes it. A new family file writes a `desc` for each block, or the test fails.
