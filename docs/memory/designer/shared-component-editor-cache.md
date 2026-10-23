---
name: shared-component-editor-cache
description: The editor's shared component cache: prefetch keeps a changed entry, the snapshot every drawn tree gets, what marks a component dirty, the flush at Save, a template placeholder.
---

## Where does the editor keep component trees?
- `_sharedCache[sid] = {name, tree, system_region_config, contentEditable, mayEditContent}`; `_sharedDirty[sid]` = unsaved edits waiting for the flush; `_sharedSnapshots[sid]` = what the stored row holds, for comparison, written and compared with `_sdSharedTreeJSON()` (it reads past `_expanded` and an empty `_attrs`, as `_pgBaselineTreeKey()` does for a page: a selection writes them).
- `prefetchShared()` runs on every tab opening and never overwrites an entry `_sdSharedChanged()` reports (dirty, or its tree differs from the snapshot); it marks that entry dirty. Symptom it fixed: a widget changed on one tab went back to the stored row as soon as another page showing it was opened, and Save wrote that back.
- While a prefetch runs, Save and Publish are disabled (`_sharedLoadInFlight`, `_updateSaveButtonState()`).
- A path that puts a changed tree into the cache marks it `_sharedDirty` (with the row's snapshot when it has it). A path that replaces the cached tree with the server row deletes the snapshot, as `prefetchShared()` does.

## When does a component get its snapshot?
- `_sdSharedBaseline()` at the end of `render()` and `renderSelectionOnly()`: every cached tree without a snapshot and not dirty gets one, so the first draw of a widget (loaded, added from the palette, made by a template) records what its row holds. After the draw, because the draw may mirror canvas state into the tree.
- A dirty entry gets none: its row may hold something else; the flush sends a dirty entry without a snapshot as it is.
- The "editing a shared component" toast shows once per component (`_sharedWarned`).

## What marks a component dirty?
- The selection: `_sdTrackSharedEditing()` runs in `render()`, `renderSelectionOnly()` and `_sdInlineSelect()` and marks the component holding `selectedNode`.
- An in-place text edit: the virtual button label's blur and the `setupInlineEditing()` commit call `_sdSharedMarkEdited(node)`. A double-click on a button label selects the button through `_sdInlineSelect()` and asks `_sdMayEdit(node, 'text')` like other text.
- A structural change marks the component that holds it: `_sdSharedOwnerOf(target)`, `_findParentAnywhere(node)` (`{parent, sid}`).
- Anything else: `_sdSharedCatchEdits()` at Save compares every snapshotted component with its tree.
- At content level only a component opened to the operator is marked (`shared-component-content-editing.md`).
- Symptom fixed (#1256): a button label of a system widget edited in place showed on the canvas but was never saved. The label span stops the click, so the button was never selected, and a widget added in the session (or re-read by a prefetch) had no snapshot for the save to compare with. On an existing widget it depended on where the first click landed.

## What happens at Save?
- `_sdSharedCatchEdits()`, then `_flushDirtyShared()` (a promise the page save awaits) sends `update` (`content_update` at content level) per dirty id, skips one equal to its snapshot, and on success keeps what it sent as the new snapshot. A refused push (a loop refusal is toasted) or an unanswered one stays dirty and keeps its old snapshot, so the next save still sends it. Tests: `tests/designer_shared_edit_test.php`.

## What if a template widget never got its layout?
- A row still holding the template placeholder (`_swIsTemplatePlaceholder()`: a root with one empty `loop_area`; `template_origin` in its settings) gets its kind's starter tree when a full-access editor loads it, marked dirty with a toast; the next save writes it.
- Without the editor: build the tree with `StyleDesigner.starterTree(kind)` on a page that defines `sdDesign.i18n` and loads `style_designer.js`, and write it with `shared_component` sub-action `update`.
