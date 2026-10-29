---
name: pg-fields-speech
description: Screen readers and panel combobox/chip fields (pg_fields.js v6): toggle name and value, listbox options, no-match line, hidden select out of Tab, focused chip deleted, named remove buttons
---

## How is a combobox toggle named?
- `drawCombobox()` gives the toggle `aria-labelledby` = the name ids + the id of its `.combobox-value` (`<id>_combobox_value`), so the chosen value is part of what is read ("Allowed Countries Turkey, Germany"). Name ids: the `label[for]` (given `<id>_label` when it has none), else the select's own `aria-labelledby`, else its `aria-label` copied onto the toggle and referenced by the toggle's own id (`<id>_combobox`). Its `aria-describedby` is copied too.
- Before this a select named only by `aria-label` (bank statement "Record" line, cart quote "Product N", offer editor pickers) gave a nameless toggle.
- Bootstrap 6 alpha's Combobox sets no role on the toggle; we add `aria-haspopup="listbox"` and `aria-controls` = the listbox id. No `role="combobox"`: the value is read through the name instead.

## What does the toggle show when nothing is chosen?
- `comboboxPlaceholder()`: `data-placeholder`, else the text of the `value=""` option, else nothing. Used for the toggle text, the empty item and the `placeholder` option of `pgUI.combobox.get()`.

## Why is the hidden select out of the Tab order?
- It kept its focus: an invisible Tab stop before the toggle whose keys changed the select while the toggle text stayed stale. It gets `tabindex="-1"` (its own value kept in `el.pgComboboxTabindex`, put back by `removeCombobox()`). `label[for]` stays on the select (the required marker reads it); a click on that label is sent to the toggle (`preventDefault()` + `toggle.focus()`).

## How are the options marked as picked?
- The items sit in a `div.pg-combobox-list[role=listbox]` (`<id>_combobox_list`, `aria-multiselectable` for a multiple select, named by the name ids) inside the `.menu`; each `<optgroup>` is a `div[role=group]` named by its `.menu-header`. The search box and status lines stay outside. The wrappers are flex columns with `gap: var(--bs-menu-gap)` set inline, so the menu looks as before.
- Items are `button.menu-item[role=option]` with `aria-selected` true/false; after every pick `syncComboboxSelected()` rewrites all of them from `.selected` (Bootstrap writes only the item it toggles).
- Server search (`serverSearch()`) swaps its items inside the listbox, not the menu; its query box's `aria-controls` names the listbox.

## How is a search with no match announced?
- Bootstrap only un-hides `.combobox-no-results`. A `div.visually-hidden[role=status]` after it gets that text on the search box's `input` event (our listener runs after Bootstrap's filter) and is emptied on a match and on `show`.

## Which chip does Delete remove?
- The typed box takes the hidden input's `<label>` text as `aria-label`.
- Bootstrap's Chips gives every chip `tabindex=0` but deletes only selected chips. `selectFocusedChip()` selects a chip on `focusin` (through the instance's `selectChip()`), unless the focus came from a press (Chips' own click selects, with Ctrl/Shift) or the chip is already `.active`. So Tab to a chip and Delete removes that chip.
- Each `.chip-dismiss` is named "Remove: <value>" (`data-pg-remove-label` + the chip's `data-bs-chip-value`), relabelled after every change.

Tests: `tests/pg_fields_test.php` (`test_pg_fields_combobox_on_bootstrap_6`, `test_pg_fields_chips_on_bootstrap_6`, `test_pg_fields_combobox_server_search`).
