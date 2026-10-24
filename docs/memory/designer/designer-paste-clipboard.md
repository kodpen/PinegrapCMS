---
name: designer-paste-clipboard
description: Designer Ctrl+V: node clipboard vs outside markup, /*pinegrap-nodes*/ prefix, conditional preventDefault, text sinks, where each paste path lands and why _sdPasteSpot is its own name.
---

## How does one Ctrl+V handle two clipboards?
- The clipboard holds either nodes copied here or markup from outside. Decide by the clipboard CONTENT, not by the key.
- Internal copy also writes to the system clipboard: `/*pinegrap-nodes*/` + node JSON (`_sdClipCopy`). Side effect: copy-paste between two editor windows works.
- The `paste` event pastes nodes if it sees this prefix, markup otherwise.
- `preventDefault()` in keydown is CONDITIONAL (`if (_sdClipStamp) return;`). Unconditional, the `paste` event would never fire and an operator who copied a node once could not paste from outside for the whole session. If the write is refused (insecure context / no permission) the old in-memory path works as before.

## What is the text bucket?
- `p`, `pre`, `code`, `h1`-`h6` and `paragraph`/`heading` content nodes (`_sdIsTextSink`): markup pasted there goes through `esc()` and becomes TEXT. The criterion cannot be the markup itself (a code sample and a real block to put on the page are byte-identical); only the operator's target distinguishes them.
- In inline text editing a paste is also reduced to plain text (so the source site's spans and colors do not leak).

## Where does each paste path put the block?
- **Paste event** (`_sdHandlePaste` → `_sdPasteNodes`, spot `_sdPasteSpot(aimed)`): our nodes off the system clipboard and imported markup. The aimed node (where the caret was) comes before the selection. Into it when it holds children (root, container, row, col, loop areas, a non-void semantic tag that is not a text sink), right after it otherwise, in the tree it lives in (`_sdAnyParent()`: page, shared component or widget tree); nothing selected → end of the page. One undo step; a shared tree that got it is marked dirty. Pasted controls pass through `_cfAdoptOnDrop()`.
- **In-memory paste** (context-menu Paste, Ctrl+V while `_sdClipStamp` is empty — no system clipboard write, or only a context-menu Copy): `_sdPasteTarget()` → `{ parent, sid }`, the copy goes right after the selection (duplication intent), in a widget's tree too; nothing selected → end of the page.
- Ctrl+V with the focus inside the canvas iframe on the in-memory path still finds the parent with `findParent(selectedNode, tree)`: inside a widget or shared component the copy goes to the end of the page.

## Why does the file have `_sdPasteSpot` and `_sdPasteTarget`?
- The whole of `style_designer.js` is one function scope: a second `function` of the same name replaces the first for every caller. Both were once called `_sdPasteTarget`; the later one (no index) answered the paste event too, so `splice(NaN)` put the block FIRST in the selection's parent, never into a selected container and at the top of the page with nothing selected.
- `tests/designer_paste_target_test.php` scans the file for names declared twice in one scope (`findNodeById` is a known pair with the same behaviour) and pins both spots.

Related: docs/memory/designer/html-import-and-paste.md
