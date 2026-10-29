---
name: sortable-init-pitfall
description: Drag-to-reorder in the panel: SortableJS set up per screen (welcome, product builder, menu tree), the arrow-key grips, no shared or jQuery UI sortable, the global-sortable trap
---

## How do the panel screens drag now?
- With the vendored SortableJS (`core/sortablejs-library.md`), set up by each screen itself with `Sortable.create()`; no shared handler touches them.
- `welcome.php` `#widgets`: items `.pg-widget`, handle `.pg-widget-handle` (the card header), filter `.no-sortable`, `delay: 300` so a click on a header button stays a click, `forceFallback` so the card follows the pointer as with jQuery UI. `onUpdate` merges the visible order into the full list (`pg_merge_widget_order()`, hidden cards keep their slots) and saves it (`pg_save_widget_order()`). The grab cursor, the empty slot (`.pg-widget-ghost`) and the opaque copy (`.pg-widget-drag`) are styled in a `<style>` block of `welcome.php`.
- `assets/js/product_builder.js` image tiles: items `.item`, no delay, `forceFallback` (the tiles carry `user-drag: none`, so a native drag never starts; it would also light up the upload drop area), filter `button, a, input` with `preventOnFilter: false` so the remove button's click lands; `onUpdate` moves the Cover badge to the first tile.
- `view_menu_items.php` tree: one Sortable per `.sort-group`, a shared `group` so an item can change level, handle `.sort-drag-handle`, `ghostClass: 'sort-placeholder'`; `onAdd` re-indents the moved item and its children. Built once, on the first Sort Items click (a `sortableReady` flag); Save Order reads the tree as before.

## How does the keyboard reorder these lists?
- Each grip is a `<button type="button">`; the arrow keys on it move the item, focus stays on it and `window.pgAnnounce()` says "Position {position} of {total}". Before, the dashboard header, the photo tiles and the `<i>` grips of the menu tree took no focus, so only a mouse could reorder (the photo cover only by removing photos).
- Dashboard (role < 2): `.pg-widget-move` at the start of the card header, outside the action buttons; Left/Up earlier, Right/Down later; saved by `pg_merge_widget_order()` then `pg_save_widget_order()`.
- Photos: `.pg-pb-move` added by `refreshCoverBadge()` (it sees tiles the picker appends) and only relabelled after that, so the focused grip survives a move; the first tile is the cover. Labels `move_photo`, `reorder_position`, `reorder_hint` sit in both label lists of `product_builder.php`.
- Menu tree: Up/Down among siblings, Right to the end of the sub-items of the item above only when it has a `.sort-group`, Left out after the parent; `updateIndentation()` re-indents. Save Order reads the tree.
- Workspace templates (`workspace_templates.js` `draggable()`): Up/Down on a task or note grip; `collect()` keeps typed values, the list is drawn anew and the same row's grip is focused again; `aria-describedby` points to one hidden `tpl_reorder_hint` span. Moving a task down lets go of its "waits for" links to tasks now below it (`drawTasks()`), as a drag does (#PR).
- Pinned by `tests/keyboard_reorder_test.php` and `tests/workspace_template_reorder_test.php`.

## Is any jQuery UI sortable left?
- No. The panel header stopped loading jQuery UI in #2762, which also removed the last `.pg-sortable` set-up from `backend.src.js` (no screen used the class). `$().sortable()` is undefined on panel screens; a list that drags creates its own `Sortable` as above.

## What was the old trap?
- `backend.src.js` once ran `$(".ui-sortable").sortable({...})` on ready (jQuery UI era). jQuery UI adds `ui-sortable` to every sortable it builds, and `.sortable({...})` on a live widget OVERWRITES its options: nothing could be dragged (`items: "a"`) or an interrupted drag left a copy (`helper: 'clone'`).
- Never set up drag lists globally by a class a library adds itself; each screen creates its own.

Related: docs/memory/ui/panel-layout-pitfalls.md, docs/memory/core/sortablejs-library.md
