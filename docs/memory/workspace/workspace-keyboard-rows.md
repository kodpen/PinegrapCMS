---
name: workspace-keyboard-rows
description: Workspace div rows act as buttons through pressKeys() (Enter and Space), the inbox Join shortcut takes keys, planning board hours print through hoursText()
---

## How does a div row act as a button on the Workspace screens?
- `pressKeys(node, action)` in `assets/js/workspace.js` sets `tabIndex = 0`, `role="button"` and a keydown that runs `action` on Enter or Space with `preventDefault()` (no page scroll). It reacts only when the key lands on the row itself (`event.target === node`), so a button inside the row (the task row's done tick) keeps its own keys. The click handler stays beside it.
- The part files get it as `h.pressKeys` from `partHelpers()`; `workspace_task_time.js` (My time) uses it for the week rows and the running-timer row.
- Rows on it: task list rows, Work Calendar day cells, the notes list items, the Work Calendar day drawer's task rows and plan-item rows, My time's rows.
- Messages are not on it: the message pane and the discussion pane carry one roving Tab stop over `.ws-msg` (`messageKeys()`, `workspace-context-menu.md`).

## Why was it needed?
- The day drawer's task and plan-item rows had a click handler only; they are the only way to open or edit a plan item from the Work Calendar (month-cell items have no handler, the day menu has no edit), so keyboard users could not edit one. My time rows had `role="button"` without keys, the running row could not take focus, and the other role=button rows answered Enter only while the board cards answer Enter and Space.

## What about the inbox Join shortcut?
- It is a `span role="button"` inside the row's `<a class="ws-record-item">`. It has `tabIndex = 0` and its own Enter/Space keydown that calls `preventDefault()` and `stopPropagation()`, so the link does not open as well.

## How are planning board hours printed?
- `hoursText(minutes)` rounds to one decimal and formats with `toLocaleString(CFG.locale || undefined, { maximumFractionDigits: 1 })`, so `day_capacity` and `cell_title` read 7,5 on a Turkish panel instead of 7.5.

## What pins it?
- `tests/workspace_keyboard_rows_test.php` (source pins). Not checked in a browser.
