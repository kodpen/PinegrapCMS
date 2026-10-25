---
name: palette-insert-at-selection
description: Where a palette double-click or command palette insert lands: in the selection, else beside it (in a widget too), where the operator may edit, wrapped as a drop; widgets stop at a system widget.
---

## Where does a double-clicked palette item land?
- `_sdInsertSpot(payload)` decides, `_sdInsertAt(spot, node, payload)` puts it there (style_designer.js). In order:
  1. Nothing (or `<body>`) selected: last on the page.
  2. The selection takes the item (`canDrop()`): last inside it.
  3. A child of the selection takes it, the last one first (a row passes a paragraph to its last column). Not when the selection takes inline content only (`_isInteractiveNode()`, `_sdIsTextSink()`: button, link, select, heading, paragraph): `canDrop()` lets a block into a span or an option in it.
  4. Else right after the selection, in the nearest ancestor that takes it (`_sdAnyParent()`), in the page tree or the shared tree it lives in.
  5. Else last on the page; `null` when the page refuses it too.
- A place counts only when `_sdCanEditNode()` allows it, so a row's editable column is found. When only places the operator may not edit take it, `_sdMayEdit()` on the first says why (outside their area, view mode) and `null` comes back: `handleDrop()`'s first question, asked before `saveState()`.
- The spot `{ parent, sibling, after, sid }` is `_swInsertRef()`'s insertCtx. `_sdInsertAt()` splices after the sibling (pushes when it is gone), rebalances a row that gets a column, marks `_sharedDirty[sid]`.
- Five handlers use it: the element palette, `_paletteInsert()` (command palette, Ctrl+Shift+P), the shared component items, the system widget "New" and existing items. "New" passes `spot.parent` and `{ sibling, after }` to `_createSystemWidgetNode()`, which creates a server row, so the check comes first.

## What wraps the inserted node?
- `_sdWrapForParent(parent, node, payload)`, shared with `handleDrop()`. At the page root (not a shared tree's root): a column in container > row, a row in a container, a palette block in a boxed container unless its componentType is a full-width section. A column into a column: in a new row. A double-click used to put a bare column at the page root.

## Why does it walk up?
- The handlers used `selectedNode || tree`: a selection that refused the item sent it to the page root, so a paragraph for a card heading in a catalog widget left the widget. Since PR #1582 it lands after the heading, in the card, and the widget is saved. A page leaf gets it right after it too, as Paste does; a selected container still takes it last inside, so double-clicks still nest (`designer-gutter-overflow.md`).
- A block or widget bringing a `<form>` walks past a form and past a widget the server wraps in one (custom form, login, account widgets): `canDrop()` keeps a form out of both at any depth (`no-form-in-form.md`).

## Why do component and widget items stop at a system widget?
- A widget tree's root, loop and cards take a `shared_ref`: the walk would put a widget in a loop row, printed once per record. For a `shared_ref` or `system_widget` payload a selection inside a system widget (`_isInsideSystemWidget()`) is replaced by the widget's placement on the open page (`_sdPlacementOnPage()`): the item lands right after the widget. A plain shared component (a header) is no boundary.

## Which placement of the widget is used?
- `_sdPlacementOnPage(sid)` walks the open page and, at each `shared_ref`, that component's cached tree (once). A placement on the page itself wins, else the first in a component on this page (a header). Components only other pages show are skipped: `_cfRefOf()` searches them all and put the item into another page's header. A widget placed twice: the first placement.

## How is it tested?
- tests/designer_palette_insert_test.php runs the helpers under Node with stubs (`canDrop` as a rule table, `ro` nodes); tests/designer_inline_drop_test.php runs `_paletteInsert()` through the real `canDrop()`.

Related: docs/memory/designer/designer-content-roles-ui-guards.md
