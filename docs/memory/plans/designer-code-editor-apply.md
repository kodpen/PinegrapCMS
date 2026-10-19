---
name: designer-code-editor-apply
description: Design for the code view editing slices (E4-E5, not built): how a typed change reaches the tree, in-place rules, new elements via the converter, undo, the sync guard
---

## How is an edit applied? (E4)
- Debounce 400 ms; parse the region of the outermost changed node from the editor's syntax tree; stop on an error node (the lint shows it).
- Existing element (its open tag starts inside a tracked open range): tag name -> `props.tag` (semantic, heading) or a conversion to `semantic` for container/row/col/component when a derived class was removed (toast); `class` -> `cssClass` minus derived tokens; `id` -> `props.id` + `_sdRetargetLabels()`; `style` -> `_writeInlineStyleValue()`; attributes with `propKey` -> the prop; the rest -> `_attrs` through the Attrs panel's rules; text: `semantic` plain (entities decoded), `content` heading/paragraph/span innerHTML.
- Element without a tracked owner: its outer HTML goes to `designer/import_fragment`; the reply's nodes are spliced at the parsed position, `_cfAdoptOnDrop()` for controls. Removed tracked node -> removed (locks permitting). Order of children follows the parsed order.
- `saveState('run:code:' + nodeId)` before the first applied change of a burst; `render()` after; `_sdCodeApplying` guards the loop so the editor keeps its text and cursor.
- Edits inside a shared component mark `_sharedDirty[sid]` (same as the canvas).

## What else must the editing slices keep? (E5)
- The switch label changes from Code to Edit; `readOnly` goes off only when `_collab.mayEdit` (view mode keeps the view read-only).
- Ctrl+S saves the design: pending editor changes are applied first (`_sdSaveShortcut()` / `saveAjax()` hook), else the last keystrokes are lost.
- A refused change (`onLockHit`) gets a toast naming the lock: head/foot rows, a locked layer, a shared component, a widget, the content role's area.
- `_pgPageUnchanged()` is unaffected: edits land in the tree, so the usual dirty check applies.
