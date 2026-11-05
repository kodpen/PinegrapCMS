---
name: list-quick-edit-keyboard
description: List quick edit keys (backend.src.js cells, the list table driver): Enter, Escape, Tab to the next row, the textarea and multiselect types, focus past the last row
---

## Which keys does a quick edit take?
- Enter and Ctrl+S save and put the focus back on the cell; Escape cancels; Tab saves and opens the same column on the next row (Shift+Tab: the row before), skipping cells still saving (`data-pg-edit-busy`). Used by products, orders, gift cards, key codes, shipping methods and the ERP lists.

## How does a several-line field read and take the keys?
- Field type `textarea` (`pg_list_edit_parse()`, includes/fn/output.php): line breaks kept as `\n` (`\r\n` and a lone `\r` read as one), tabs and other control characters become spaces, ends trimmed, `required` and `max` as for `text`; list column `'editable' => 'textarea'`; drawn as a `textarea` (`.pg-list-table-field-lines`). `quickChanged()` reads a stored `\r\n` as `\n`, so an untouched field never saves.
- In the list table driver's quick edit mode (`pg_list_table_edit.js`), a `textarea` column (`'type' => 'textarea'`: comments Message, theme and design file Description) takes Enter and Shift+Enter as a new line; Ctrl+Enter saves and goes on as Enter does in a one-line field, Ctrl+S saves, Tab saves by leaving it (`change`), Escape puts the stored text back.

## How does a several-choice field read and take the keys?
- Field type `multiselect` with `options` (values without a comma): posted and stored in `data-pg-edit-value` as "3,7"; `pg_list_edit_parse()` gives a list in the options' order, refuses a value not offered, `required` needs one. List column `'editable' => 'multiselect'`. Used by contact groups and shipping method zones.
- Drawn as a group of check boxes (`.pg-list-table-choices`, `choiceGroup()`); Tab and Space move and tick inside it, leaving the group saves (`focusout`), Enter and Ctrl+S save, Escape puts the stored choice back. The group has `tabIndex = -1`: Safari and Firefox on macOS do not focus a clicked box, so without it a tick by mouse or touch never left a focused group and was not saved. `quickChanged()` compares the values as a set.

## Where does focus go on Tab past the last row?
- On the edited cell (`td[data-pg-edit]` has `tabindex="0"`). `step()` returns false when no row is left and `commit()` then calls `state.cell.focus()`. Before, the input had already left the page (`send()` or the restored `innerHTML`) and focus fell to `<body>` with nothing announced (`tests/list_edit_focus_test.php`).

## When does a choice list save?
- On a pointer pick or a pick in its opened list at once; after an arrow key on the closed list only on Enter, Tab, Ctrl+S or blur (`select-commit-on-pick.md`, #3477).
