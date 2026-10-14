---
name: designer-content-roles-ui-guards
description: Designer content-role UI guards: _sdCanEditNode vs _sdMayEdit, every guarded mutation path, WeakSet perf rule, _sdGuardItem, content chrome.
---

## Why is the per-node permission check not a tree walk?
- `_sdCanEditNode` is asked per node and answered by `_sdInEditableArea`. The first version walked up with `findParent`, which scans the WHOLE tree each call: O(n^2) (200 nodes 1 ms, 500 = 3 ms, 1000 = 17 ms, doubled because canvas and tree ask separately). Now it reads a `WeakSet` (`_sdEditableSet`, mark delegated top-down) built once per tree change and invalidated in `saveState()` and `render()` (one runs before every mutation, the other before every paint): 0 ms at 1000 nodes.
- Rule: a predicate called per node during render must not call a helper that walks the tree; prepare the answer in one pass.
- `_sdInEditableArea` uses `_sdAnyParent`: `findParent(n, tree)` returns null inside a shared component's own tree and inheritance stops at the first node, so every child of a marked block looked unmarked.

## Which function do I call, and where is it asked?
- `_sdCanEditNode(node, what)` is the SILENT predicate; `_sdMayEdit` wraps it and shows a toast on refusal. Render calls the silent one, interaction calls the talking one (`_sdMayEdit` while painting = one toast per node).
- Asking places (add a new mutation path to this list): every mutating item of both context menus (`_sdGuardItem`), tree drag (`draggable` AND the drop target), Delete/Backspace, `handleDrop`, `onDelete`, inline text editing, canvas toolbar handle and delete button, `renderProperties`. The first version forgot menus and tree dragging and a user could delete the whole page on the first try.
- `_sdGuardItem` FADES the item, never removes it: a vanished menu item reads "software is broken", a faded one reads "I can't do this". In multi-select it is all or none (deleting three of five leaves an unwanted page).

## Other content-role UI rules
- `_sdApplyContentChrome()` was called once at boot but the palette and settings modal are built later. It is idempotent; applied at boot and again after 300 / 1200 ms. The chrome must run after what it hides exists.
- Never offer an unreachable path: the welcome screen showed three create-design cards to content roles while `add_system_style.php` requires a designer. A button either works or is not there.

Related: docs/memory/designer/designer-content-roles-save-gate.md
