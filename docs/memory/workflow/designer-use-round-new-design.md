---
name: designer-use-round-new-design
description: Use round UR2 (PR #11839): the New design window used in a browser; what was found, the eight fixes, what was left and what could not be run
---

## What was used, and how?
- The New design window (`designer/new-design-window.md`) on a `setup_sandbox.sh` instance: every start (Blank, Template, Figma, Canva, From a document, ZIP file, Pasted markup) at 1440 × 900 and 390 × 844, light and dark, and once by the keyboard alone.
- Ten pages: ten PNG files given to the Canva start at once, through the colours card to "Create design" and the Sketch tool. The empty design: Blank to the start questions.
- Figma connected and refused: a fake token stored in the sandbox; Figma itself was never reached.

## What was found and fixed?
- The pane's title was a bare `h3` (about 28 px) with its line stuck to the form under it. Now the Design examples' pane head: `.pg-sm-heading.sd-ns-head` with `.pg-title-section` and `.pg-sm-desc.pg-text-2`, 20 px before the form.
- From a document drew a second Cancel in the foot (two at 1440, four stacked rows at 390). `pg_document_rows.js` draws Cancel only when its caller passes `cancel`; the window passes none.
- A start's quiet foot words (the Canva hint, "read on this site", the document's Back) sat right of the window's Cancel, which stood at the far left. They are now first (`order: -1`), Cancel stays beside the filled button.
- On open the focus was on Close, so Up and Down did nothing. On `shown` the chosen start takes the focus, unless a start's own field already has it.
- The pane is a Tab stop (tab pattern) but had `outline: none`. It shows the panel's focus ring.
- On a phone the list stands over the pane: a tap changed nothing in view. The chosen pane is scrolled into view.
- Canva: files added together came in the browser's order (1, 10, 2 …). They come in their names' number order (`inPageOrder()`, `tests/canva_start_test.php`).
- Figma: the empty state's icon was left of its centred words; "Manage in Connections" broke over two lines; the refused-token alert ran the pane's width while the card and field stop at 560 px. Fixed in `designer_home.css`.
- From 768 px the window keeps one height (`min(52rem, 100dvh − margins)`); it jumped as starts of other heights were chosen.

## What was left, and why?
- Canva colours card and Figma list: Back stands between Cancel and the next button. Moving a control is the architect's call; not asked yet.
- Pane titles differ in kind: "Figma", "Template" beside "Start from Canva pages", "Start from a document"; Turkish "başla" beside "başlayın". A reworded label is the architect's.
- The template cards' pictures stayed white in the sandbox within a few seconds; not followed.
- The document's text box shows its placeholder in a monospace font.
- Canva's "Create design" opens the Sketch tool at 10 % on a phone (UR3's ground).

## What could not be run?
- Figma beyond the link field: reading a file, the frame list, the variables card, the import and its report need a real token and Figma's API.
- ZIP and paste end in the editor's own boxes; only the window's part was used.
