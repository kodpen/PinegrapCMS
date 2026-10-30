---
name: explorer-list-headings
description: File manager list view (view_folders.php): headings sort from a button and carry aria-sort; column edges resize by keyboard, any pointer, double-click fit and a menu reset
---

## How does a heading sort without a mouse?
- A sortable `<th>` holds `button.th-sort` (label and caret, `data-sort-key`); only the sorted heading carries `aria-sort` (ascending/descending), the caret is `aria-hidden`.
- `sortByColumn(key, refocus)` redraws through `renderContent()`; a keyboard press (`event.detail === 0`) passes `refocus` and the redrawn button takes the focus again. A mouse click does not (the screen blurs clicked buttons, `explorer-keyboard.md`).
- Why: before, only a click on the th sorted, nothing said the order, and eight sort keys (seo, account, ERP status, folder, quantity, enabled, impact, access) have no entry in the Sort by menus.

## How does a column edge move without a drag?
- `.col-grip` is a focusable `role="separator"` (`aria-orientation="vertical"`, `aria-valuenow/min/max`), named "Resize column {var:1}". It stays outside the heading's button.
- Focused: Left/Right narrow and widen by 16 px (Shift 64), Home puts the default back. `keyBelongsToControl()` leaves those keys to it (it has a tabindex).
- Any pointer drags it: `pointerdown` + `setPointerCapture`, `touch-action: none`, 16 px wide under `(pointer: coarse)`. Before it listened to mousedown only, so a tablet could not resize at all.
- A double click fits the column to its heading and widest drawn cell (`fitColumnWidth()`, first 500 rows); it used to reset to the default, which Home and the menu now do.
- The Columns submenu shows "Reset column widths" (`columnWidthsReset()`) once any width is remembered; "Show every column" still only brings hidden columns back.
- Every width goes through `setColumnWidth()`: clamped to `COLUMN_MIN_WIDTH`..`COLUMN_MAX_WIDTH` (48..1200), written on the `<col>` and the grip's value, remembered in `pg_explorer_columns` (forgotten when equal to the default, `listColumnsAll(true)`), then `syncListMinWidth()`.

Test: `tests/explorer_keyboard_test.php` (source pins).
