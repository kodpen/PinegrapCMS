---
name: designer-paste-clipboard
description: Designer Ctrl+V: node clipboard vs outside markup, /*pinegrap-nodes*/ prefix, conditional preventDefault, text sinks, where pastes land (into, or beside a node that looks like the paste).
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
- Every path calls `_sdPasteNodes(nodes)` → spot `_sdPasteSpot()`: the paste event (our nodes, imported markup), the context-menu Paste, Ctrl+V while `_sdClipStamp` is empty (designer document and canvas iframe). One undo step, the last copy selected, order kept, a shared tree that got it marked dirty, controls through `_cfAdoptOnDrop()`.
- Only the selection decides; nothing selected → end of the page. A click selects, so the caret's spot is not asked (after Ctrl+Z: the first canvas element or a locked row's label).
- The rule, from the visible tree only: BESIDE the selection when it looks exactly like a pasted node (`_sdPasteRepeats()`), else INTO it when it holds children and `_sdPasteFits()` takes every node, else beside.
- "Looks exactly like" (`_sdPasteShape()`): the node and subtree as JSON with sorted keys, without `_id`, `_expanded`, empty lists/objects (selecting writes `_attrs: []`; PHP returns `[]` for `{}`) and a form control's `name`/`_cf.ids` (a form widget renames copies). Markup compares the nodes it became. So the copied node, any earlier copy, a Ctrl+D or context-menu duplicate, a copy after undo/redo or a reload all take it beside; an edited copy or another container takes it inside; two identical empty boxes count as copies. No paste history is kept.
- Holds children: root, container, row, col, loop areas, a non-void semantic tag that is not a text sink. `_sdPasteFits()` = `canDrop()`: not into a selected locked element, a row takes columns, a list items, a column goes beside a column; the copy's own lock flag does not count.
- Beside = right after the selection in its tree (`_sdAnyParent()`: page, shared component or widget), without the drop rules (beside a node inside a locked element is inside it).

## Why is there one spot function?
- `style_designer.js` is one function scope: a second `function` of the same name replaces the first for every caller. Two were once called `_sdPasteTarget`; the in-memory one (no index) answered the paste event too, so `splice(NaN)` put every block FIRST in the selection's parent. The in-memory paths now share `_sdPasteNodes()`.
- `tests/designer_paste_target_test.php` scans for names declared twice in one scope (`findNodeById` is a known pair) and pins the spot, drop rules, the look rule (earlier copy, duplicates, undo/redo, reload, edits), both paths and the stale caret.
- Open: a context-menu Copy after a Ctrl+C leaves `_sdClipStamp`, so Ctrl+V pastes the older system clipboard.
- Open: a canvas Shift+click range keeps `selectedNode` on the first node clicked, so its copies land after that one, between the originals.

Related: docs/memory/designer/html-import-and-paste.md
