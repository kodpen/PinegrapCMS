---
name: list-quick-edit-keyboard
description: List quick edit (data-pg-edit cells in backend.src.js) from the keyboard: Enter, Escape, Tab to the next row, where focus goes past the last row
---

## Which keys does a quick edit take?
- Enter and Ctrl+S save and put the focus back on the cell; Escape cancels; Tab saves and opens the same column on the next row (Shift+Tab: the row before), skipping cells still saving (`data-pg-edit-busy`). Used by products, orders, gift cards, key codes, shipping methods and the ERP lists.

## Where does focus go on Tab past the last row?
- On the edited cell (`td[data-pg-edit]` has `tabindex="0"`). `step()` returns false when no row is left and `commit()` then calls `state.cell.focus()`. Before, the input had already left the page (`send()` or the restored `innerHTML`) and focus fell to `<body>` with nothing announced (`tests/list_edit_focus_test.php`).

## When does a choice list save?
- On a pointer pick or a pick in its opened list at once; after an arrow key on the closed list only on Enter, Tab, Ctrl+S or blur (`select-commit-on-pick.md`, #3477).
