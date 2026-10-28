---
name: sortable-init-pitfall
description: Drag-to-reorder in the panel: SortableJS set up per screen (welcome, product builder, menu tree), no shared or jQuery UI sortable, the global-sortable trap
---

## How do the panel screens drag now?
- With the vendored SortableJS (`core/sortablejs-library.md`), set up by each screen itself with `Sortable.create()`; no shared handler touches them.
- `welcome.php` `#widgets`: items `.pg-widget`, handle `.pg-widget-handle` (the card header), filter `.no-sortable`, `delay: 300` so a click on a header button stays a click, `forceFallback` so the card follows the pointer as with jQuery UI. `onUpdate` merges the visible order into the full list (`pg_merge_widget_order()`, hidden cards keep their slots) and saves it (`pg_save_widget_order()`). The grab cursor, the empty slot (`.pg-widget-ghost`) and the opaque copy (`.pg-widget-drag`) are styled in a `<style>` block of `welcome.php`.
- `assets/js/product_builder.js` image tiles: items `.item`, no delay, `forceFallback` (the tiles carry `user-drag: none`, so a native drag never starts; it would also light up the upload drop area), filter `button, a, input` with `preventOnFilter: false` so the remove button's click lands; `onUpdate` moves the Cover badge to the first tile.
- `view_menu_items.php` tree: one Sortable per `.sort-group`, a shared `group` so an item can change level, handle `.sort-drag-handle`, `ghostClass: 'sort-placeholder'`; `onAdd` re-indents the moved item and its children. Built once, on the first Sort Items click (a `sortableReady` flag); Save Order reads the tree as before.

## Is any jQuery UI sortable left?
- No. The panel header stopped loading jQuery UI in #2762, which also removed the last `.pg-sortable` set-up from `backend.src.js` (no screen used the class). `$().sortable()` is undefined on panel screens; a list that drags creates its own `Sortable` as above.

## What was the old trap?
- `backend.src.js` once ran `$(".ui-sortable").sortable({...})` on ready (jQuery UI era). jQuery UI adds `ui-sortable` to every sortable it builds, and `.sortable({...})` on a live widget OVERWRITES its options: nothing could be dragged (`items: "a"`) or an interrupted drag left a copy (`helper: 'clone'`).
- Never set up drag lists globally by a class a library adds itself; each screen creates its own.

Related: docs/memory/ui/panel-layout-pitfalls.md, docs/memory/core/sortablejs-library.md
