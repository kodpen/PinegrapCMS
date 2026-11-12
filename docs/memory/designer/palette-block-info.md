---
name: palette-block-info
description: Visual Page Editor Blocks list hover card: the one-line description of every core ready-made block (_sdBlockInfo), how a line is written and translated, the test that holds a new block to it
---

## Where does a block's description line come from?
- From `_sdBlockInfo()` in `style_designer.src.js` (right after `_sdPalInfo()`): a map from `componentType` to one `_sdT()` line, built on first use. `_sdBlockDesc(item)` reads it for a palette row; `_sdPalTipHTML()` shows it under the block's drawing ([[palette-block-sketch]]).
- A description `d` in `_sdPalInfo()` wins over it; the Components rows keep theirs.
- It covers the core list of `_sdBlockPaletteItems()` only (127 blocks when it was added, #8758). Family blocks get a `desc` in their own file's `items` ([[designer-block-family-files]], [[designer-palette-drawings]] P5–P6).

## How is a line written?
- One sentence of at most 140 characters, ending in a full stop, no ". " inside: what the block adds, read from the tree its builder returns (the parts in order, columns, the band's colour), not its label again.
- Name parts by what a visitor sees ("a dark band", "three cards with an icon"), not by class names; say "in the main colour of the design" for `text-bg-primary`.
- No two blocks share a line: say what sets a look apart from its neighbours (centred, split, with a form, on a picture).
- Turkish only through `php tools/tr_add.php` (a `--from=<json>` file for many lines at once).

## What holds a new block to it?
- `tests/designer_block_info_test.php` reads the core list and the map from the source: it fails for a core block without a line, a line for no core block, a line that is not one `'type': _sdT('text')` literal, a line written twice, and a line that is not one short sentence.
- `tests/designer_block_sketch_test.php` checks under Node that the hover card of a block shows its line.
- A PR that adds a core block writes its line in the same PR; while two PRs race, the later one writes it.
