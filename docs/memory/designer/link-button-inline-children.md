---
name: link-button-inline-children
description: Spans and icons inside a link or button, links back into a heading or paragraph but never into a link or button, why an inline edit keeps markup, where the label prints.
---

## How can a link or button hold a span?
Two ways, and both survive a save:
- **In its text.** A content `link` (also `span`, `text`) prints `props.text` as markup. The HTML import writes `<a><span class="badge">2026.4.9</span><span>…</span></a>` this way (`pg_di_inner_html()`); the spans are not nodes of their own.
- **As child nodes** dropped into it. Into `<a>`, `<button>`, headings and `<p>` only inline drops go (`_isInlineOnlyDrag()`): inline semantic tags, content `text` / `span` / `icon` / `image`, a heading printed as an inline tag (the blocks' "New!" badge), component `badge`; headings and `<p>` also take a link (next section).

## Can a link go back into a heading or a paragraph?
- Yes: a semantic `<a>` or content link goes into `h1`–`h6` and `<p>` when all inside it is inline and holds no link or button (`_sdInlineLinkDrag()`); a link card with a `<div>` stays out. `_isInlineOnlyDrag()` used to refuse every link, so a headline link (`<h3><a>`, PR #1169 blocks) or a link in a sentence could not go back once dragged out.
- Never into a link or a button, at any depth (`canDrop()` Rule 0): `_sdDragCarriesInteractive()` (the node, one below it, or `<a>` / `<button>` in a content node's markup) against `_sdInInteractive()` (target or an ancestor). This also closed old holes: a link into a span inside a link or button, a span holding an `<a>` into a link.
- Canvas highlight, drop zones, Overview tree and click-to-add all ask `canDrop()`: one rule for every path. Tests: tests/designer_inline_drop_test.php.

## Why did a double-click turn such a link into plain words?
- The inline editor read link / span / text nodes back with `textContent`. Stored markup and `textContent` always differ, so even a double-click with nothing typed wrote the words without the spans. The canvas kept showing the spans until the next redraw; Save stored the flattened text (pinegrap.com's release link: "2026.4.913 October 2026 · …", PR #468).
- Now `_sdInlineEditValue(el, rich, markup)` returns `innerHTML` when the edited element holds any element (`keepsMarkup`: content types in `_SD_RICH_TEXT_CONTENT` other than heading / paragraph, which are full rich text with the toolbar). Plain text is still trimmed `textContent`, so a plain link edits as before. Enter still ends the edit; no formatting toolbar.
- A session that ends with the value the canvas showed when it started (`origShown`) writes nothing and puts the stored text back: `&middot;` and `·` are the same text and an idle double-click must not swap one for the other. Escape restores with `innerHTML`.
- A paste during the edit is still reduced to plain text (designer-paste-clipboard).

## Why could the palette Span not go into a button?
- `_isInlineOnlyDrag()` accepted content `text` / `icon` / `image` only. The palette's Span is content `span`, so the drop was refused on every link and button (PR #468).

## Where does a semantic `<a>` / `<button>` print its label beside its children?
- Canvas (virtual text) and `generateHTML()`: after the children by default (`textPosition` 'end'), before them with 'start'. Component `btn` does the same on the server.
- `_render_tree_node()` printed a semantic label first whatever `textPosition` said, so a span placed in front of the label on the canvas came out behind it on the page. It now follows `textPosition` for `a` / `button` (PR #468, tests/designer_button_label_test.php). Pages keep their stored code until saved again.
- Other semantic tags (div, li, td) still print text first on the server and after the children on the canvas. The importer never gives them both: mixed content becomes span children.

Related: docs/memory/designer/node-text-and-whitespace.md, docs/memory/designer/palette-component-atomic.md, docs/memory/designer/html-import-and-paste.md
