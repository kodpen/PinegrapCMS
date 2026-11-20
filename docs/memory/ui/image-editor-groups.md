---
name: image-editor-groups
description: Design Canvas layer groups (GC1a): l.grp on each layer, Group and Ungroup keys, a press picking the group and a click a member, order, copy, delete, the list's group rows, server draw
module: image_editor
---

## Where does a group live?
- On its layers: `l.grp` lists the groups a layer is in, the outermost first (`['g1', 'g2']`: g2 inside g1). No group entry elsewhere, so a scene's layers carry their groups and an older document opens unchanged. No schema.
- A group has no look of its own: its layers draw as they do ungrouped, on the stage, in an export and in the server's draw (`pg_image_draw_known()` takes `grp`). Group ids are `g<n>`, unique over every scene (`M.groupNewId()`).
- Its members lie next to each other in `doc.layers`; `M.groupTidy()` gathers them where the topmost lies and ends a group of fewer than two layers (after a delete, a copy, an ungroup).
- Code: the GROUP section of `pg_image_editor_model.js`; the editor side in `pg_image_editor_layers.js`; labels in `pg_image_editor_strings_layers()` (`keyGroup`, `keyUngroup` in access).

## What do Group and Ungroup do?
- Group (Ctrl/Cmd+G, the Groups row of Select's panel, the context menu): the selection's items in the group it sits in (a layer, or a whole group inside it) become one group on the place of the topmost (`M.groupMake()`); fewer than two items: nothing. The new group stays picked.
- Ungroup (Ctrl/Cmd+Shift+G, the same places): the outermost group the selection holds whole is taken apart (`M.groupOfSelection()`, `M.groupBreak()`); its layers stay picked and in place, in the groups around it.
- One undo step each. Both on the help sheet with the layer keys; the menu shows them for two layers or more.

## How is a group picked?
- A press on a layer picks its outermost group (`M.groupPick()`); a click on a picked group picks the item one level in (`M.groupDeeper()`, on the release, so a drag still moves the group). While the selection sits inside a group, a press picks the sibling item there. Shift adds or takes out a whole item (`M.groupToggle()`); a selection box brings whole groups (`M.groupExpand()`).
- A group is picked as its members: move, nudge, align, opacity, lock, hide, duplicate and delete work on them as on several layers.
- Order (`M.groupReorder()`): a picked group moves whole, a member only inside its group.
- Duplicate, copy and paste make new groups (`M.groupCopy()`): the groups around the copied layers are left, the copies go on top.

## What does the layers list show?
- `M.groupRows()`: a group is one row (its icon, "Group n (count)", show/hide and lock for all its layers) with a chevron opening it to its members, indented by depth. A press on its name picks it (Shift adds); a group the selection lies inside opens by itself unless closed by hand (`groupsOpen`).

## What is left for GC1b?
- Size and turn of a picked group as one box, a group's own opacity, Animate's keys on a group, Group and Ungroup in the bar over the object and the phone's bar (`docs/memory/plans/design-canvas-groups.md`).

## How is it checked?
- `tests/image_editor_groups_test.php`: every model command under Node, presses, keys, order, duplicate and delete on an editor, the menu, the help sheet, and the server's plan of a grouped document against the ungrouped one. Not run in a browser: the stage and the export draw from the same layers as before (`paintLayers()` ignores `grp`).
