---
name: overview-chains-hints
description: Designer Overview (#10235): a chain of single-child wrappers folded into one line, opened by a click or by a selection in it; a hint after a name the page repeats; the pure functions
---

## Which rows fold into a chain line?
- Under any drawn row, a run of wrappers that are open and hold exactly one child each, two or more long, is one `li.sd-tree-chain`: "4 levels hidden: Account area › … › Signed in" (first and last name, `_sdTreeChainLabel()`), then the element after the chain at the same indent.
- A wrapper (`_sdTreeIsWrapper()`): container, row, column or a `div`/`section`/`nav`/`header`/`footer`/`main`/`article`/`aside` with no text, no editable-area mark, no lock and no note. Content elements, lists, `li`, shared components and sections (the body's children) never fold.
- The rows after a fold keep their real level: `buildTreeItems(ul, node, depth, lvl)` draws at `depth`, the "Level N" badge counts `lvl` (kept in `data-lvl` for the in-place redraw, `_sdTreeRowsRedraw()`).

## When is a chain drawn open?
- After its line is clicked (the line is a button; its first node goes into `_sdTreeChainsOpen`, a WeakSet, nothing written on the tree, so the saved page and the dirty check never see it; an undo's new nodes fold again).
- While a node of it is selected or in the multiple selection: the whole chain, below the selected node too (`_sdTreeChain()` returns its `run`, drawn row by row through `_sdTreeChainShown`). A selection below the chain leaves it folded; the path still names every level.

## Which names get a hint?
- At each `renderTree()`, `_sdTreeCountNames()` counts the row names of the page (shared components' trees included). A row whose name occurs more than once, and that has no designer label (`_label`), shows `.sd-tree-hint` "· My orders" after the name.
- The hint (`_sdTreeRowHint(node, 40)`): the element's own text (markup stripped), a picture's file name, a link's target when it has no text; else the first of these within its first 60 descendants. Cut to 40 characters with "…", then by CSS before the label ever is.

## Where is the test?
- `tests/designer_overview_rows_test.php`: a chain of four folded and opened, a selected chain open with its whole run, a lone wrapper, a closed wrapper, an editable area and content not folded; 61 links named alike with 61 hints; a picture's file name; a link's target; a long hint cut; the label's CSS never shrinking while a hint can.
