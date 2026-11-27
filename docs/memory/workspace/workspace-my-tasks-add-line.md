---
name: workspace-my-tasks-add-line
description: My Tasks' "Add a task…" line above the groups of Mine: Enter saves through ws_task_save given to the reader, Tab opens New task with the title, a refusal under the field in the server's words
---

## Where does the line stand?
- Above the first group of the Mine view, in the list's card but outside its body (`list.insertBefore(addLine.node, listBody)` in `startTasks()`), so a reload of the list keeps what is typed and the focus. With no task it stands above the empty screen.
- Shown only in Mine with "Open tasks", no search, no Filter tag and the agents toggle off: anywhere else a task given to the reader with no date would not show up in the list. Hidden in My time. Worked out at the start of every `load()`.

## What does Enter do?
- Sends `ws_task_save` with `{ title, assignees: [me] }`, the New task drawer's own action; no endpoint of its own. No date, so the task lands under No date; normal priority, no department.
- Saved: the field is cleared (unless something new was typed meanwhile), the drawer's "Task created" toast, then `load(task_id)`. An empty title, a save already on its way or Enter during input-method composition does nothing.
- `needs_confirm` (a clash with the reader's leave or load; `ws_assignment_check()` finds one only with dates or a department, so rare here) opens the New task drawer with the title, which asks as it does for New task.

## What does Tab do?
- Tab (no Shift, Ctrl, Alt or Meta) from a typed title opens the New task drawer with the title filled in (`taskDrawer.open(0, { title }, …)`); its save clears the field and loads the list with the new id. An empty field or Shift+Tab moves the focus as usual.

## How is a refusal said?
- The server's `message` (the store's words, e.g. "A task needs a title." or the rule on whom tasks may be given to) stands under the field as `.pg-field-help.pg-field-error`, with `aria-invalid="true"` on the input and a polite live region; no toast. Typing clears it. An answer with no message gives the Workspace's generic error text.

## What does the fresh id do?
- `load(fresh)` hands the id to `PGWsMyTasks.list(…, fresh)`: the group holding that task is drawn open for that drawing even when folded, without changing `ws-tasks-folded`. The drawer's save and `taskMenu()` already call `load(task_id)`, so a task saved there opens its group too.

## What does it look like?
- The Design examples field: `label.pg-input.ws-mt-add-field` holding a `bi-plus-lg` icon in `--pg-fg-3` and a bare input (placeholder "Add a task…", label "Add a task", at most 255 characters).
- One line of help, "Enter adds it to your tasks; Tab opens it in New task.", shows while the field has the focus and no refusal; under 576 px it is left out, as it names Tab. CSS: `.ws-mt-add*` after the `.ws-mt-*` block of `backend.src.css`.
- Tests: `tests/workspace_task_add_line_test.php` pins the action, the keys, the refusal and where the line stands.

Related: docs/memory/workspace/workspace-my-tasks-screen.md, docs/memory/ui/look-new-parts.md, docs/memory/ui/screen-composition-rules.md
