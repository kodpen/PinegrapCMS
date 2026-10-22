---
name: shared-component-editor-cache
description: The editor's shared component cache: prefetchShared() never overwrites a dirty entry, _sharedDirty and snapshots, what marks a component dirty, the flush at Save, repairing a template placeholder.
---

## Where does the editor keep component trees?
- `_sharedCache[sid] = {name, tree, system_region_config, contentEditable, mayEditContent}`; `_sharedDirty[sid]` = unsaved edits waiting for the flush; `_sharedSnapshots[sid]` = the JSON the stored row holds, for comparison.
- `prefetchShared()` runs on every tab opening (it reads every widget the page shows) and never overwrites an entry marked `_sharedDirty`: until Save the cache is the only copy of the change. Symptom it fixed: a widget changed on one tab (or the layout a template leaves to the editor) went back to the stored row as soon as another page showing it was opened, and Save wrote that back.
- While a prefetch runs, Save and Publish are disabled (`_sharedLoadInFlight`, `_updateSaveButtonState()`): a save in that window used to cut shared content out of the saved tree.
- A new path that puts a tree into the cache writes `_sharedDirty` (and `_sharedSnapshots` for the comparison). A path that replaces the cached tree with the server row deletes the snapshot, as `prefetchShared()` does.

## What marks a component dirty?
- The selection, not the panels: `_sdTrackSharedEditing()` runs in both `render()` and `renderSelectionOnly()` and marks the component holding `selectedNode` (the first time also a snapshot and the "editing a shared component" toast). A new selection path goes through one of the two.
- A structural change inside a cached tree marks the component that holds it: `_sdSharedOwnerOf(target)` and `_findParentAnywhere(node)` (`{parent, sid}`) tell which tree a node lives in.
- At content level only a component opened to the operator is marked (`shared-component-content-editing.md`).

## What happens at Save?
- `_sdSharedCatchEdits()` first compares every snapshotted component with its tree and marks the changed ones: after a save the selection can stay inside a component, the panels change the selected node without marking anything, and that edit would never be written.
- `_flushDirtyShared()` (a promise the page save awaits) sends `update` (`content_update` at content level) per dirty id, skips one equal to its snapshot, keeps a refused or unanswered one dirty (a loop refusal is toasted), and keeps what it sent as the new snapshot. Do not delete the snapshot at save.

## What if a template widget never got its layout?
- A row still holding the template placeholder (`_swIsTemplatePlaceholder()`: a root with one empty `loop_area`; `template_origin` in its settings) gets its kind's starter tree when a full-access editor loads it, marked dirty with a toast; the next save writes it.
- Without the editor: build the tree with `StyleDesigner.starterTree(kind)` on a page that defines `sdDesign.i18n` and loads `style_designer.js`, and write it with `shared_component` sub-action `update`.
