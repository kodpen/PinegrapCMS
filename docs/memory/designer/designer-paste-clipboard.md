---
name: designer-paste-clipboard
description: Designer Ctrl+V: node clipboard vs outside markup, /*pinegrap-nodes*/ prefix, conditional preventDefault, text sinks, where every paste path lands (into, beside, repeated paste), one _sdPasteNodes().
---

## How does one Ctrl+V handle two clipboards?
- The clipboard holds nodes copied here or markup from outside; decide by its CONTENT, not by the key.
- Internal copy also writes the system clipboard: `/*pinegrap-nodes*/` + node JSON (`_sdClipCopy`); copy-paste between two editor windows works too.
- The `paste` event pastes nodes if it sees this prefix, markup otherwise.
- `preventDefault()` in keydown is CONDITIONAL (`if (_sdClipStamp) return;`). Unconditional, the `paste` event never fires and no outside markup could be pasted after one node copy. If the write is refused (insecure context / no permission) the in-memory paste runs.

## What is the text bucket?
- `p`, `pre`, `code`, `h1`-`h6` and `paragraph`/`heading` content nodes (`_sdIsTextSink`): markup pasted there goes through `esc()` and becomes TEXT. The markup cannot decide (a code sample and a real block are byte-identical); the element the paste landed on (`_sdNodeFromEventTarget`) does. That node decides nothing else.
- In inline text editing a paste is reduced to plain text (no source-site spans or colors).

## Where does a paste land?
- Every path calls `_sdPasteNodes(nodes, key)` → spot `_sdPasteSpot()`: the paste event (our nodes, imported markup), the context-menu Paste, Ctrl+V while `_sdClipStamp` is empty (designer document and canvas iframe). One undo step, the last copy selected, order kept, a shared tree that got it marked dirty, controls through `_cfAdoptOnDrop()`.
- Only the selection decides; nothing selected → end of the page. A click selects, so the caret's spot is not asked (after Ctrl+Z: the first canvas element or a locked row's label).
- INTO the selection only when it holds children (root, container, row, col, loop areas, a non-void semantic tag that is not a text sink), it is not a repeat, and `canDrop()` takes every node (`_sdPasteFits()`: not into a selected locked element, a row takes columns, a list items, a column goes beside a column; the copy's own lock flag does not count).
- A repeat (`_sdPasteRepeats()`) goes BESIDE: the selection is a copied node (same `_id`), or the last copy (`_sdLastPaste`: what the last paste selected, or a Ctrl+D duplicate of it or of a copied node) while the clipboard holds the same thing (`key`: the nodes' JSON, the same on both paths; the markup for an import, whose nodes get new ids). So Ctrl+C, Ctrl+V, Ctrl+V and Ctrl+C, Ctrl+D, Ctrl+V give siblings; pasting into a container takes selecting another one.
- The last copy is held by reference (an undo hands its id out again). Undo/redo rebuild every node, so each snapshot stores its id and tree (`_sdPasteMemo()`) and undo/redo find it by that (`_sdPasteRelink()`); the id names the copy only in its snapshot.
- Beside = right after the selection in its tree (`_sdAnyParent()`: page, shared component or widget), without the drop rules (beside a node inside a locked element is inside it).

## Why is there one spot function?
- `style_designer.js` is one function scope: a second `function` of the same name replaces the first for every caller. Two were once called `_sdPasteTarget`; the in-memory one (no index) answered the paste event too, so `splice(NaN)` put every block FIRST in the selection's parent. The in-memory paths now share `_sdPasteNodes()`.
- `tests/designer_paste_target_test.php` scans for names declared twice in one scope (`findNodeById` is a known pair) and pins the spot, drop rules, repeats (also after undo/redo and Ctrl+D), both paths and the stale caret.
- Open: a context-menu Copy after a Ctrl+C leaves `_sdClipStamp`, so Ctrl+V pastes the older system clipboard.
- Open: a canvas Shift+click range keeps `selectedNode` on the first node clicked, so its copies land after that one, between the originals.

Related: docs/memory/designer/html-import-and-paste.md
