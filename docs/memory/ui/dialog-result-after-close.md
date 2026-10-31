---
name: dialog-result-after-close
description: A Bootstrap 6 dialog or drawer hands its result to the page only once closed; the writing box's + menu windows put in nothing before that (#4196); the caret after a card
---

## Why must a dialog close before its result goes into the page?
- A Bootstrap 6 dialog or drawer is a modal `<dialog>`: `hide()` adds `.hiding` and calls `close()` only after the animation (`pg-ui-adapter.md`), and until then the page under it is inert. A box there takes no focus, so `document.execCommand('insertHTML')` into a contenteditable does nothing and returns false, with no error.
- Setting a plain field's value works while inert; focusing it, moving its caret or any editing command does not. So a window that writes into a box of the page does it in `pgUI.dialog.hide(el).then(...)` (or `pgUI.drawer`), never right after `hide()`.

## Where is it done?
- `modal()` in assets/js/workspace_editor.js gives each window `finish(callback)`: hide, then the callback. The table, checklist, code and calculation windows hand their markup over through it (`win.finish(function () { done(…); })`), for the channel's writing box and the notes alike.
- `hideOffcanvas(node, then)` in workspace.js runs `then` once the drawer has closed: the + menu's "Pull from elsewhere" (`blockPuller()`) and the saved replies' Add to message (workspace_replies.js) use it.
- add_order.js: the product box takes the focus in `pgUI.dialog.hide(…).then()` after the new-customer window and Empty the cart; focused right after `hide()` the focus fell to `<body>`, so the next scan went nowhere (`commerce/counter-sale-keyboard-focus.md`).

## What was the symptom?
- Owner, 2026-10-31: the writing box's + menu put neither a table nor a calculation into the message; the window closed and the box stayed empty. Checklist, code block, pulled blocks and saved replies did the same. It came with the panel's switch to Bootstrap 6 (`866af2d9f`), when the windows became `<dialog>`s.

## Why does the emoji picker hand over before it closes?
- The same trap from the other side: `closeCtx()` gives the focus back to the writing box, and a box focused that way had its caret at its start, so the emoji went in before the words (`emojiPicker()` in workspace.js; channel box and the drawers' boxes alike). `pick()` calls `onPick()` first, then closes.

## Where is the caret after a card?
- On the line after it: Chrome leaves the caret before what insertHTML brought in when it ends in a non-editable card, so the next words or card went above it (a calculation over the table it follows). `insertBlock()` marks the new card (`data-ws-new`), puts the caret after its line break and adds a second break when that one is the last (a last break draws no line).
- Tests: `tests/workspace_editor_windows_test.php`.
