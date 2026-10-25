---
name: palette-insert-at-selection
description: Where a double-clicked palette item or a command palette insert lands: into the selection, else beside it (inside a widget too); components and widgets stop at a system widget (#1582).
---

## Where does a double-clicked palette item land?
- `_sdInsertSpot(payload)` decides, `_sdInsertAt(spot, node)` puts it there (style_designer.js, beside the zero-argument `_sdPasteTarget()`). In order:
  1. Nothing (or `<body>`) selected: last on the page.
  2. The selection takes the item (`canDrop()`): last inside it.
  3. A child of the selection takes it, the last one first (a row passes a paragraph to its last column): last inside that child.
  4. Else right after the selection, in the nearest ancestor that takes it, walking up with `_sdAnyParent()`: in the page tree or in the shared tree the selection lives in.
  5. Else last on the page; `null` (nothing added) when the page refuses it too.
- The spot is `{ parent, sibling, after, sid }`, the insertCtx shape `_swInsertRef()` takes. `_sdInsertAt()` splices after the sibling (or pushes when the sibling is gone), marks `_sharedDirty[sid]` and calls `expandAncestorsAnywhere()`.
- A column that lands in a column (a selected column, or a leaf in one) goes into a new row there, as `handleDrop()` wraps a column dropped onto a column; never a column directly in a column.
- Used by five handlers: the element palette, `_paletteInsert()` (command palette), the shared component items, and the system widget "New" and existing items. Each keeps its own tail (selection, recents, `_cfAdoptOnDrop()`, the shared-cache prefetch). The "New" handler passes `spot.parent` and `{ sibling, after }` to `_createSystemWidgetNode()`.

## Why does it walk up?
- The five handlers used `selectedNode || tree`: a selection that refused the item sent it to the page root, so a card heading selected in a catalog widget sent a paragraph outside the widget. Now it lands after the heading, in the card (every card on the live page), and the widget is saved.
- `_paletteInsert()` had no child probe and marked no `_sharedDirty`, so Save could skip a widget it had changed.

## Why do component and widget items stop at a system widget?
- A widget tree's root (type `root`) and its loop and card nodes accept a `shared_ref`, so the walk would put a widget inside a loop row, printed once per record. For a `shared_ref` or `system_widget` payload, a selection inside a system widget (`_isInsideSystemWidget()`) is replaced by that widget's placement on the open page (`_sdPlacementOnPage()`), and the walk starts there: the item lands right after the widget.
- A plain shared component (a header) is not a boundary: a component may still go inside it, as a drag allows.

## Which placement of the widget is used?
- `_sdPlacementOnPage(sid)` walks the open page's tree and, at each `shared_ref`, the cached tree of that component (once each). A placement on the page itself wins; else the first one inside a component on this page (a header).
- Cached components that only other pages show are skipped. `_cfRefOf()` searches them all, which put the item into another page's header (not shown, saved there).
- The same widget placed twice on a page: the first placement, as a node in a shared tree does not know which one was clicked.

## What changed for page leaves?
- A selected leaf in the page body (a paragraph, a heading, a widget's placement) now also gets the new item right after it, where it used to go to the end of the page. This is the rule the context-menu Paste follows. A selected container still takes the item last inside it, so consecutive double-clicks still nest (`designer-gutter-overflow.md`).

## How is it tested?
- tests/designer_palette_insert_test.php cuts the three helpers out under Node with stubs (`canDrop` as a rule table, a parent map with sids); tests/designer_inline_drop_test.php runs `_paletteInsert()` through them.

Related: docs/memory/designer/inline-drop-links-buttons.md, docs/memory/designer/designer-find-node-for-el.md
