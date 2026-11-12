---
name: palette-block-sketch
description: Visual Page Editor Blocks list hover card: the block drawn from the tree its build returns (_sdBlockSketch), what each node becomes, what is left out, the cache, the test, the known limits
---

## What does a block's hover card show?
- Hovering or focusing a row of the Blocks list shows the tip card (`_sdPalTipHTML()`) with a drawing of the block and its name, then the block's description line ([[palette-block-info]]).
- The drawing is made from the tree the block's builder returns, not stored: a block added to the core or a family file is drawn unasked.
- An item with its own drawing key `v` in `_sdPalInfo()` (the Components rows) keeps that drawing. An item with neither shows no card.

## How is a block drawn?
- `_sdBlockSketch(tree)` (section "Block drawings" in `style_designer.src.js`) walks the tree as the page would lay it out and returns one SVG, viewBox 200×110, the same palette as `_sdPalDrawing()`.
- Heading: a dark bar, thicker the higher the heading (tag or `h1`–`h6`, `display-*` class). Text: grey lines. Picture: a grey box with a mountain, its ratio from the node or the ratio class. Card, bordered or shadowed box: an outlined white box. Button: an accent pill (outline buttons as a stroke, a dropdown with a caret). Badge: a small tinted pill. Icon: a dot. Rule: a thin line.
- Rows put columns side by side by their `col-*` spans (desktop size, offsets kept, at most three lines); a flex row follows its justify and align classes; a navbar spreads its parts.
- A dark band (`bg-dark`, `data-bs-theme="dark"`, a background picture) turns its text white; tone classes (`text-primary`, `bg-success-subtle`, alerts) give their colour; a link's contents take the link colour.
- The drawing is fitted by its own box: scale between .45 and 1.6, centred; a tall block is cut at the bottom edge.

## What is left out?
- What the site shows only on demand: modal, offcanvas, toast, a closed collapse or dropdown menu, carousel slides and tab panes that are not `active`, `d-none` at desktop, the `hidden` attribute, hidden inputs, scripts and script-only custom HTML.
- A dropdown that is the block itself (the theme switcher) shows its menu, or it would draw one button.

## When is it built?
- On the first hover: `_sdBlockSketchOf(item)` builds the tree through `_sdDragTree()` (the dry tree a drag builds, kept in `_sdPaletteShapes`) and keeps the SVG in `_sdBlockSketchCache` per `componentType`. A builder that throws draws nothing and is not retried.
- Only items of `_sdBlockPaletteItems()` are drawn this way.

## Why is `_sdBlockSketch()` marked `/* @pg-site-output */`?
- It reads the Bootstrap 5 class names of the site page the block is dropped into (`modal`, `carousel-item`, `dropdown-menu`); `check_bs6.php --strict` would read them as panel code to convert.

## How is it tested?
- `tests/designer_block_sketch_test.php` cuts the palette code, `createFromPalette()` and every family file out of the core and runs them under Node: every core and family block is built and drawn, none empty, broken or with fewer than two marks; two `col-lg-6` headings draw two bars on one line; a second hover builds nothing; the hover card of a block, a component and an unknown item.
- A new block that draws thin usually hides its content behind one of the left-out classes above.

## What does it not do well yet?
- A navbar is a thin strip in the middle of the card; a carousel caption is drawn below its picture; a very tall block is drawn small and cut.
- Added in #8699; the band walk of [[prototype-thumbnails]] was the model.
