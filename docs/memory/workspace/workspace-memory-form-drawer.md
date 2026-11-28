---
name: workspace-memory-form-drawer
description: The Memory tab's New entry / Edit form as a form drawer beside the list (MF1): head, fields, foot, refusals, the ask before closing, the saved row lit, /memory, mount()'s start
---

## What does the drawer hold?
- New entry and a row's Edit open the look's form drawer (`ws-memory-form`, from the right, `min(46rem, 100vw)`) built in MF1; the list, its search words and its folds stay behind it. Owner (2026-11-28): the form in the list's place made the memory look reset.
- Head: New entry or Edit entry, under it where it is kept (This channel's memory, or "General memory, shared by every agent channel."). Body from the kit's form parts (`h.form`), in order: Title, Project (a datalist of the projects already listed), Text (8 rows, grows with what is typed), Tags, Expires on, the Pinned switch. Foot: on Edit Delete (`part.remove()`, the row's confirm) left, Cancel quiet and Save right.
- A refusal with a `field` is said under that field, any other under the fields; the drawer stays open. Esc, the cross or Cancel with changed fields asks (Changes not saved / Discard the changes).
- After Save the drawer closes, the list is read again, the saved row (`data-ws-memory-id`) is scrolled into view and lit (`.ws-memory-row-saved`, 2.4 s).
- A general record is written with `general=1`. In the General memory drawer the form drawer opens over it.
- `/memory` without a text draws the list and opens the drawer over it. `mount()`'s third parameter is `start`: named `fill`, the list's hoisted `fill()` hid it and the tab always opened on New entry (fixed in MF1).

## How is it tested?
- `tests/workspace_channel_look_test.php` (`test_workspace_channel_panes_memory_form_is_a_drawer_beside_the_list`): the form clears nothing of the list, opens `ws-memory-form`, asks before closing, names `start`.

Related: docs/memory/workspace/workspace-memory-tab.md, docs/memory/ui/screen-composition-rules.md, docs/memory/workspace/workspace-drawer-form-parts.md
