---
name: explorer-tree-keyboard
description: The File and Catalog Manager tree on the explorer's own screen (in the main menu panel) from the keyboard: focusable link rows with aria-current, arrows with aria-expanded, Enter/Space
---

## What can a keyboard and a screen reader do in the tree?
- Every `div.tree-row` (quick rows, Shared Folders/Backups, ERP Files and its folders, folder and group rows, Pinned/Recent) is `role="link" tabindex="0"`; the row showing in the listing carries `aria-current="page"` beside `.active`, moved only by `markTreeRow()` (`highlightTree()`, `highlightErpTree()`, and the same ternary in the row markup).

## How do the branch arrows say their state?
- A branch arrow is a `span.tree-toggle` with `role="button" tabindex="0"`, `aria-expanded` and an `Expand`/`Collapse` name (`L.expand`/`L.collapse`): `treeToggleMarkup()` draws it, `syncTreeToggle()` keeps it in step from the click handler, `setErpTreeOpen()` and both create-in-tree paths. A span, not a button, because those paths turn a childless row's spacer into an arrow later. Folder and group rows are named by `aria-label` (the name), so the arrow's name stays out of the link's.

## Which keys does the tree take?
- One keydown on `#explorer_tree_pane` turns Enter/Space on a focused row or arrow into the existing click; an inline rename's input keeps its keys. Before, the tree was mouse-only on the explorer's own screen (other screens draw it through `explorer_side.js`).
- Outside an input, `keyBelongsToControl()` already leaves keys on a `[tabindex]`/`[role=button]` element to it, so the listing's shortcuts do not take Enter/Space from the tree when the menu is hidden and the pane stays in the screen.

Test: `tests/explorer_tree_keyboard_test.php` (source pins).

Related: docs/memory/ui/explorer-keyboard.md, docs/memory/ui/explorer-menu-tree.md, docs/memory/ui/current-and-pressed-state.md
