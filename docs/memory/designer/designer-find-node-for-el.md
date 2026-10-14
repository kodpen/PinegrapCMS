---
name: designer-find-node-for-el
description: Resolve a canvas element to a node with _sdFindNodeForEl(id, el) and _sdAnyParent(node), not findNodeById, because shared component trees are separate.
---

## Why not findNodeById for canvas elements?
- `findNodeById(id, tree)` knows **only the page tree**. A node inside a widget lives in the shared component's own tree (`_sharedCache[sid].tree`), so the result is `null` and the caller exits silently. The right-click menu **never opened** for exactly this reason: `preventDefault()` never ran, so the browser's own menu appeared.

## What is the rule?
- For a `data-sd-id` coming from canvas DOM use `_sdFindNodeForEl(id, el)`; for the parent use `_sdAnyParent(node)`.
- Lookup order: `data-sw-sid` -> page tree -> whole cache. The stamp already exists to prevent **id collisions across trees**.
- The same bug existed in the `[data-modal-section-id]` / `[data-modal-dlg-id]` handlers.
