---
name: workspace-mention-picker
description: The @ and # lists of Workspace writing boxes (drawPicker()): which boxes use them, where a list opens (pickerPlace() at the caret, fitPicker() over the composer), the kind chips
---

## Which boxes use the list?
- One component: `pickerCheck()` → `openPicker()` → `drawPicker()` of the app in `assets/js/workspace.js` (people after `@`, records and the kind chips after `#`, and the `:`, `;`, `/` lists). The channel and discussion composers are the app; `richField()` lends it to every formatted box (a task's description and notes, the channel summary, the notes screen, the approval, status and saved-reply drawers of `workspace_approvals.js`, `workspace_status.js`, `workspace_replies.js`); `tokenField()` to a task note box without the formatted editor.
- `refSearchField()` (a kind select and a search box) draws its own `.ws-picker` under its input; not this list.

## Where does a list open?
- A formatted box (`richField()` sets `caretPicker: true`, the notes screen `notesMode`): at the caret. `placePicker()` adds `.ws-picker-caret` (fixed, `width: min(26rem, 100vw - 1rem)`), measures it, asks `pickerPlace(caret, field, view, size)` and sets left, top and width inline, max-height only when the room is shorter than the list (a cap at its own height rounded down drew a row scrollbar for a pixel).
- `pickerPlace()` (pure): under the caret line (6px gap) unless the list fits only above it or above has more room; height cut to that room, 96px at least; left edge at the caret, moved left so the right edge stays in; kept to the field's column when that is 240px wide or more (narrowed to it), else to the window; 8px from the view's edges. Positions are measured from the fixed box's own corner, so a drawer that still moves cannot shift it.
- `pickerView()`: the visual viewport (a phone's keyboard shrinks only that), its top under `.software-navbar`; the bar and the main menu rail (`.software-sidebar`) are fixed at z-index 1030 over the list.
- The composer's list stays over the box at its left (`.ws-picker`: absolute, `left: 1rem`, `bottom: calc(100% - .25rem)`); `fitPicker()` cuts its max-height to the room above within `pickerView()`. A task note textarea's list sits over its box (`.ws-note-compose .ws-picker`).

## What did it fix?
- PR #8388: in the channel summary and the drawers' boxes the caret list kept `.ws-note-compose .ws-picker`'s `left: 0; right: 0; width: auto` once fixed, so it ran from 8px to the window's right edge, its left part under the 250px main menu, and all kind chips fitted in one long row. Now every edge is set inline. A task description's list opened under the whole box (`.ws-desc-field .ws-picker`, removed); now at the caret too.

## How do the kind chips behave?
- One row (`.ws-picker-types`, `nowrap`, `overflow-x: auto`, thin scrollbar) inside the list's width: 22 kinds would take four rows of a 26rem list. A mouse wheel turns the row sideways; the chosen kind is scrolled into view.

## How was it checked?
- `tests/workspace_picker_place_test.php` runs `pickerPlace()` under Node (wide field beside the menu, both field edges, phone, drawer, small field, up and down, keyboard, no room) and holds the wiring.
- dev.pinegrap.com, 2026-11-09, built-in browser, dark and light: the summary at 2200, 1280, 900 and 375 px with the caret at the field's left and right edges, at the window's foot (opens upward) and in a 375×420 window; `@` after `## `, `#` after an `@` chip; a new task's description; the status drawer; the composer at 375×380. Not run live: the notes screen (it saves while typed), a real phone keyboard.
