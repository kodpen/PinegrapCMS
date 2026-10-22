---
name: inline-drop-links-buttons
description: Designer drops: links and buttons go back into headings and paragraphs, never into a link or button; a label holds one control; a palette block is judged by what it holds.
---

## Which links and buttons go into a heading or a paragraph?
- A link or a button (semantic `a` / `button`, component `btn`, content `link`, a content heading printed as `<a>` / `<button>`) goes into `h1`–`h6` and `<p>` when all inside it is inline and holds no link or button, in its markup neither (`_sdInlineInteractiveDrag()`). A link card or a button holding a `<div>` stays out.
- `_isInlineOnlyDrag()` used to refuse every link and button, so a headline link (`<h3><a>`, PR #1169 blocks), a link in a sentence or the accordion header's `<button>` (a content heading with `tag: 'button'`) could not go back once dragged out.

## What never goes into a link or a button?
- Nothing interactive, at any depth (`canDrop()` Rule 0): `_sdDragCarriesInteractive()` (the dragged tree, or `<a>` / `<button>` in a content node's markup) against `_sdInInteractive()` (the target or an ancestor).
- This closed old holes: a link into a span inside a link or a button, a span holding an `<a>` into a link, the accordion header button into a span inside a link (`_isInteractiveNode()` did not count a heading printed as `<button>`).

## What may a label take?
- One control (Rule 0b): no button, input (other than `type="hidden"`), select, textarea or `btn` printed as `<button>` / `<input>` goes into a `<label>`, or an element inside it, that already holds one (`_sdLabelOf()`, `_sdHoldsControl()`). The moved node itself does not count, so a control moves within its own label. A `btn` printed as `<a>` is a link and may go in.

## How is a palette item judged?
- By the tree it would add (`_sdDragTree()`): `createFromPalette()` builds it once per type and extra, kept in `_sdPaletteShapes`. While `_sdPaletteDry` is set the build writes no block styles (`_sdEnsureBlockStyles()`), and the id counter is put back afterwards.
- Only the top-level type used to count, so a ready-made block holding links went into a span or a div inside a link, and a form field's input into a label that already had one.
- Double-click insert and the command palette (`_paletteInsert()`) pass the payload a drag does (`source`, `type`, `extra`). They passed `{ type }` only, so neither a block's content nor a semantic item's tag was seen.

## Where do these rules apply?
- The canvas highlight and zones, drop zones, the Overview tree and click-to-add all ask `canDrop()`: one rule for every path. The dragged tree is looked at once per drag (`_sdIact`, `_sdCtl` on the payload), since dragover asks on every mouse move.
- Tests: tests/designer_inline_drop_test.php cuts `canDrop()` and its helpers out of style_designer.js and runs them under Node.

Related: docs/memory/designer/link-button-inline-children.md, docs/memory/designer/text-inner-elements.md
