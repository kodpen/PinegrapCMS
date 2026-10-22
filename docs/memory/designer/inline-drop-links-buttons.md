---
name: inline-drop-links-buttons
description: Designer drops: links and buttons go back into headings and paragraphs, never into a link or button; a label holds one control; a palette block is judged by what it holds.
---

## Which links and buttons go into a heading or a paragraph?
- A link or a button (semantic `a` / `button`, component `btn`, content `link`, a content heading printed as `<a>` / `<button>`) goes into `h1`–`h6` and `<p>` when all inside it is inline, at any depth, and holds no link or button, in its markup neither (`_sdInlineInteractiveDrag()`). A link card or a button holding a `<div>` stays out.
- `_isInlineOnlyDrag()` used to refuse every link and button, so a headline link (`<h3><a>`, PR #1169 blocks), a link in a sentence or the accordion header's `<button>` (a content heading with `tag: 'button'`) could not go back once dragged out.

## What never goes into a link or a button?
- Nothing interactive, at any depth (`canDrop()` Rule 0): `_sdDragCarriesInteractive()` (the dragged tree, or `<a>` / `<button>` in a node's own markup) against `_sdInInteractive()` (the target or an ancestor).
- Interactive here is `_sdIsInteractiveEl()`: `_isInteractiveNode()` plus a content heading printed as `<button>` / `<a>`, minus `<input type="hidden">`. A node's own markup is a content node's text or an atomic component's `toHTML()` other than `btn` (`_sdNodeMarkup()`): the language switcher prints a dropdown button and links.
- Closed holes: a link into a span inside a link or a button, a span holding an `<a>` into a link, the accordion header button or the language switcher into a span inside a link.

## Why does the accordion header button take no child?
- A content heading prints `props.text` only; `_render_content_html()` and the canvas export ignore its children. A span dropped into it showed on the canvas and was gone from the page.
- So `_isInteractiveNode()`, which decides what takes inline children (canDrop Rule 1+2, the canvas inside-start / inside-end zones), leaves it out, and canDrop ends at `allowed.content = []`. A content `link` prints its children and still takes them.

## What may a label take?
- One control (Rule 0b): no button, input (other than `type="hidden"`), select, textarea, `btn` printed as `<button>` / `<input>`, or markup holding one, goes into a `<label>`, or an element inside it, that already holds one (`_sdLabelOf()`, `_sdHoldsControl()`). The moved node itself does not count, so a control moves within its own label. A `btn` printed as `<a>` is a link and may go in.

## How is a palette item judged?
- By the tree it would add (`_sdDragTree()`): `createFromPalette()` builds it once per type and extra, kept in `_sdPaletteShapes`. While `_sdPaletteDry` is set the build writes no block styles (`_sdEnsureBlockStyles()`), and the id counter is put back afterwards.
- Only the top-level type used to count, so a block holding links went into a span inside a link, a form field's input into a label that had one.
- Double-click insert and the command palette (`_paletteInsert()`) pass a drag's payload (`source`, `type`, `extra`), not `{ type }` alone, which hid a block's content and a semantic item's tag.

## Where do these rules apply?
- The canvas highlight and zones, drop zones, the Overview tree and click-to-add all ask `canDrop()`. The dragged tree is looked at once per drag (`_sdIact`, `_sdCtl` on the payload), since dragover asks on every mouse move.
- The Overview tree drops only where its last dragover found a legal place (`li._dropPos`, cleared on each dragover and dragleave), and asks `canDrop()` for every node of a multi-selection before moving any. It used to fall back to "inside" unasked: a link went into a span inside a link.
- Not covered: a shared component (`shared_ref`) is a black box, its inner tree is not looked at.
- Tests: tests/designer_inline_drop_test.php cuts `canDrop()`, its helpers and `_paletteInsert()` out of style_designer.js and runs them under Node.

Related: docs/memory/designer/link-button-inline-children.md, docs/memory/designer/text-inner-elements.md
