---
name: explorer-picker-state
description: File and Catalog Manager pickers and menu ticks expose their state (aria-pressed, aria-current); transfer and channel picks keep focus; empty lists announced
---

## Which controls say their state, and how?
- Move to / Copy to / Add to group rows (`renderTransferTargets()`) and Send to a Workspace channel rows (`renderWsSendChannels()`): `aria-pressed` `"true"` on the picked row beside `.active`, `"false"` on the others.
- Filters menu items: `aria-pressed` from `state.filters[key]`; the tick icon is `aria-hidden`. The active-filter chips in `#explorer_filters_bar` are named `Remove filter: {var:1}` (`L.remove_filter`); the plain label read like a filter, not a removal.
- `menuCheckItem()` (Columns, tile size, the context menu's View and Sort submenus) prints `aria-pressed` from `checked`; the Preview panel item starts `"false"` and `syncPaneButtons()` keeps it in step.
- Grid/List buttons print `aria-pressed="false"`; `syncViewButtons()` sets both beside `.active`.
- The Files scope menu (`syncFileScope()`) sets `aria-current="true"` on the current scope and removes it from the rest: the scope narrows the list in place (`current-and-pressed-state.md`).

## Why does focus go back to the row after a pick?
- A pick redraws the list with `innerHTML`, which removed the focused button: focus fell to the body and the next Tab started at the top of the dialog. `focusPickerRow()` focuses the redrawn row with the same `data-target-id` / `data-channel-id`.

## How are empty lists announced?
- `announcePickerEmpty(picker, text)` writes "Nothing matches what you typed." or "There is no channel you can write in." into `#explorer_search_status` once when it appears; `pickerEmptySaid` is reset on each fresh load so a reopened dialog says it again.

Test: `tests/explorer_picker_state_test.php` (source pins).
