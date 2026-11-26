---
name: sketch-reader
description: The Prototype view's sketch reader (assets/js/pg_sketch_reader.js, window.PGSketchReader): boxes drawn in a frame in, sections with kinds and unsure marks out; its input, rules, output and test
---

## What does the reader take and give?
- `PGSketchReader.read(frame, boxes)`: frame `{x, y, w, h}`; boxes `{id, x, y, w, h}` in the same coordinates, optional `text` (typed word), `mark` (`cross` picture, `lines` text), `kind` (chosen in the chip's menu), `parent` (box id; without it a box nine tenths inside a bigger one is its child).
- Answers `{sections, skipped}`. A section: `id` (its first box's, so a later "Build again" can match it), `kind`, `widget` (catalog, blog, booking or null), `boxes`, `buttons`, `columns`, `heading` (first typed word), `picture`, `top`, `height`, `unsure`, `reason`, `options`.
- Kinds (`KINDS`, the chip menu's order): header, intro, features, cards, text, picture, gallery, form, cta, footer, catalog, blog, booking. Labels are the chip's job (`_sdT()`), not the reader's.
- Pure, no DOM; loaded in the designer part `sketchbuild` (`designer/sketch-build.md`), which reads with `h` the lowest box's bottom: with `h: 0` every box is skipped as outside the frame.

## Which rules read a sketch?
- Sizes against the frame's width (zoom does not matter). A side under 1% is skipped, as is a box half outside the frame or with a bad number; `skipped` lists them.
- A box at most 35% wide and 7% high is a button. Top boxes overlapping in height by half are one band = one section, from the top.
- Order of evidence: a chosen `kind` wins; then a typed word naming one kind (English or Turkish, folded: `fold()`, `wordKinds()`); then place and shape.
- Shape: thin full-width band at the top = header, at the bottom = footer (small boxes only there too); a tall band (30%) right under the header or the top = intro; equal boxes in a row (within 20%) = gallery (pictures only), cards (each picture and text) or features; a box whose children are such a row reads as that row; two or more wide thin stacked fields with a button = form; a low band of text with a button = cta; a lone button = cta; cross = picture, else text.

## When is a section unsure?
- `empty`: one box with nothing in it (not a header or footer); `words`: typed words naming two kinds; `mixed`: unlike boxes side by side. The guess is `kind`, `options` the kinds to ask about; a sure section has `reason: null`, `options: []`. The tool asks, never guesses in silence (`sketch-overview.md`).

## Where is it tested?
- `tests/sketch_reader_test.php` runs it in Node (skipped without Node).
