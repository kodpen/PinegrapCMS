---
name: designer-my-library-manage
description: "Manage library" (ML3): where it opens, the window's rows and foot, rename, Move to group, order by grip or Alt+arrow, delete, the server calls and the order they keep
module: designer_library
---

## Where does "Manage library" open?
- The last row of the "My library" group in the Blocks list (hidden while the list's search is typed) and the command palette row `act_library_manage`. No button. Designer roles only (full access); a content role has neither.
- The window is its own part `librarymanage` (`assets/js/style_designer_librarymanage.js`), handed to full access only and run the first time "Manage library" is chosen. `manage()` in the part `mylibrary` loads the library, then calls `open(lib)` with a handle (`items`, `setItems`, `find`, `post`, `redraw`, `changed`); the core holds the palette row only.

## What does the window show?
- "Manage library", the item count, "Search the library…" (name and group; "No item matches" when nothing does), then the items in the drawn order: "No group" first, then each group with its count.
- A row: grip, checkbox, the item's drawing (`_sdBlockSketch()`), name, kind and element count, a three-dot menu (Rename, Move to group, Export, Delete).
- The foot: nothing chosen, "Changes are saved as you make them.", Import, Export all, Done; items chosen, "N selected", Export, Move to group, Delete, Done. Shift with a click chooses the rows between. At phone width the window takes the screen and Done the full width.
- Export and Import are drawn only when the screen hands over the part `libraryfile` (ML4) and call its `exportItems(ids)` / `importFile()`; its `sd-library-changed` event makes the part `mylibrary` read the library again and the open window draw it again.

## How is each change made?
- Rename: the name becomes a field (menu, double-click, Enter or F2 on a row); Enter or blur saves, Escape puts the old name back, an empty name is not saved.
- Move to group: the groups in use, "No group", "New group…" (typed). The items go after the target group's last item; a new group goes last.
- Order: a row dragged by its grip (pointer events, so mouse and touch); dropped on a row it takes that row's group, on a group's head it goes first in it. Alt+Arrow up/down moves the focused row, across a group's edge into the next group (above the first group: no group). Ordering is off while a search is typed.
- Delete: the only step that asks (`pgConfirm`), naming the item when it is one; also the Delete key on a row. No bin.
- Each change is drawn at once and sent; the answer is the list drawn again (window and Blocks group). A refusal puts the list back and shows the reason in the window.

## What does the server do?
- Sub actions `library_rename` (id, name), `library_move` (ids, group), `library_order` (order: id and group pairs in drawn order), `library_delete` (ids), each `pg_library_may('manage')`, answering `items` as `library_list` does.
- `pg_library_manage_changes()` (pure) works out what to write; every change but a delete numbers the whole library again in the drawn order (`pg_library_drawn_order()`: no group first, then each group where its first item stands), so the order kept is the order seen. Only items whose name, group or order changed are written.

## How is it tested and measured?
- `tests/designer_library_test.php`: drawn order, rename, move, order, delete.
- Measured (#11608): core `style_designer.min.js` +187 B (+50 B gzip, the palette row and three kit entries); part `mylibrary` 13.7 to 14.8 KB (the group's row and the handle); part `librarymanage` 14.8 KB (5.2 KB gzip), loaded on first use. `node tests/js/designer_render_timing.js` unchanged within spread.
