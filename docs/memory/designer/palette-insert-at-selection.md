---
name: palette-insert-at-selection
description: Where a double-clicked palette item or a command palette insert lands: into the selection, else beside it (inside a widget too); components and widgets stop at a system widget.
---

## Where does a double-clicked palette item land?
- `_sdInsertSpot(payload)` decides, `_sdInsertAt(spot, node)` puts it there (style_designer.js, beside the zero-argument `_sdPasteTarget()`). In order:
  1. Nothing (or `<body>`) selected: last on the page.
  2. The selection takes the item (`canDrop()`): last inside it.
  3. A child of the selection takes it, the last one first (a row passes a paragraph to its last column): last inside that child.
  4. Else right after the selection, in the nearest ancestor that takes it, walking up with `_sdAnyParent()`: in the page tree or in the shared tree the selection lives in.
  5. Else last on the page; `null` (nothing added) when the page refuses it too.
- The spot is `{ parent, sibling, after, sid }`, the insertCtx shape `_swInsertRef()` takes. `_sdInsertAt()` splices after the sibling (or pushes when the sibling is gone), marks `_sharedDirty[sid]` and calls `expandAncestorsAnywhere()`.
- Used by five handlers: the element palette, `_paletteInsert()` (command palette), the shared component items, and the system widget "New" and existing items. Each keeps its own tail (selection, recents, `_cfAdoptOnDrop()`, the shared-cache prefetch). The "New" handler passes `spot.parent` and `{ sibling, after }` to `_createSystemWidgetNode()`.

## Why does it walk up?
- The five handlers used `selectedNode || tree` and, when the selection refused the item, fell back to the page root. A card heading selected inside a catalog widget sent a paragraph to the end of the page, outside the widget. Now it lands right after the heading, in the card, and the widget is saved with the next save (live page: in every card).
- `_paletteInsert()` had no child probe and marked no `_sharedDirty`, so Save could skip a widget it had changed.

## Why do component and widget items stop at a system widget?
- A widget tree's root (type `root`) and its loop and card nodes accept a `shared_ref`, so the walk would put a widget inside a loop row, printed once per record. For a `shared_ref` or `system_widget` payload, a selection inside a system widget (`_isInsideSystemWidget()`) is replaced by that widget's placement (`_cfRefOf()`: the page tree first, then a component that holds it, such as a header), and the walk starts there: the item lands right after the widget.
- A plain shared component (a header) is not a boundary: a component may still go inside it, as a drag allows.
- With the same widget placed twice on a page, the first placement is used.

## What changed for page leaves?
- A selected leaf in the page body (a paragraph, a heading, a widget's placement) now also gets the new item right after it, where it used to go to the end of the page. This is the rule the context-menu Paste follows. A selected container still takes the item last inside it, so consecutive double-clicks still nest (`designer-gutter-overflow.md`).

## How is it tested?
- tests/designer_palette_insert_test.php cuts both helpers out under Node with stubs (`canDrop` as a rule table, a parent map with sids); tests/designer_inline_drop_test.php runs `_paletteInsert()` through them.

Related: docs/memory/designer/inline-drop-links-buttons.md, docs/memory/designer/designer-find-node-for-el.md
