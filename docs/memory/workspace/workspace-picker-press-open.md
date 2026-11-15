---
name: workspace-picker-press-open
description: Why a press inside the @ and # list of a Workspace writing box (scrollbar, gap, padding) keeps it open: the root mousedown guard, blurPicker() and pickerStaysOpen() (#9580)
---

## Why does a press inside the list not close it?
- The list's root (`.ws-picker`, made in `openPicker()` of `assets/js/workspace.js`) prevents `mousedown`'s default: a press on the strip's or list's scrollbar, a gap between chips, the padding, the hint or empty line keeps the focus and caret in the box. Scrollbars still drag (Chromium, checked).
- Its `pointerdown` notes the press (`pickerPressStart()`); `pointerup` / `pointercancel` ends it and gives the focus back to the box if a browser moved it (Chromium moves it to the drawer when the guard is bypassed).

## How does a box close the list on blur?
- `tokenField()` and `richField()` call `blurPicker(box)`: 150 ms later the list closes unless the pure `pickerStaysOpen(pressInside, focus)` keeps it. focus is `box`, `picker`, `outside` or `none` (`pickerFocusPlace()`). It stays while a press from inside lasts or the focus is in the box or the list.
- The composers have no close on blur; the root guard keeps their caret on a scrollbar press.

## What did it fix?
- PR #9585 (#9580): in the New task drawer's Description, pressing the `#` list's strip scrollbar (or any spot that was not a chip or row) closed the menu. The same fix went to `saRecordPicker()`, both lists of `workspace_mail.js` (address suggestions, `;` saved replies) and `pg_style_suggest.js`. `refSearchField()` (closes on an outside click) and `add_order.js` (whole list guarded) were not affected.

## How is it tested?
- `tests/workspace_picker_blur_test.php`: `pickerStaysOpen()` under Node, and the wiring.
- Sandbox, Playwright Chromium: description, note box (plain and formatted), composer, 390 px touch. Firefox and Safari not run.
