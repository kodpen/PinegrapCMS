---
name: canvas-binding-line
description: An application's binding of a table to a canvas shows in the channel as a line with Undo (CO7d): the system line and its ws_refs, the reader's words, who may undo, what Undo puts back
---

## When is a line written?
- When an application binds a table to a canvas over the API (`bound_by_kind` app, `ws_canvas_table_bind()` in `canvas_tables.php`), new or with a changed access. Binding again with the same access writes none; a person's binding in the panel, and a team devices session binding in the person's name, write none.
- `ws_canvas_binding_line_post()` (`includes/workspace/canvas_binding_lines.php`): a system line in the canvas's channel, "@handle bound a table to Sales board · reads". The stored words never name the table: a reader of the channel may not reach it.

## How does the line know its binding?
- `ws_refs` rows on the line (source_type message), no new column: `canvas_bind` (the canvas's message), `canvas_bind_table`, `canvas_bind_now` and `canvas_bind_was` (access as 1 none, 2 read, 3 write; ws_refs skips id 0), `canvas_bind_app`, and after Undo `canvas_bind_undone` (the person). Deleting the line deletes them.

## What does a reader see?
- Payload `canvas_binding` {text, can_undo, undone_by} (`ws_canvas_binding_lines_map()`): the line in the reader's words, the table named when they reach it ("@handle bound Products to Sales board · reads"), else "a table". The agent is its `@handle` (`ws_agent_tags()`), else the application's name.
- `workspace.js` `canvasBindingUndo()` draws Undo after the time, or "Undone by <name>".

## Who may undo, and what does it do?
- Panel action `ws_canvas_binding_undo` {message_id: the line}. Only while the binding still stands as the application set it (same access, same application, `ws_canvas_binding_line_current()`, pure); else "The binding changed since, so it is not undone."
- A new binding is taken off (`ws_canvas_table_unbind_check()` decides who); a changed one is bound again with its earlier access in the person's name (`ws_canvas_table_bind_check()`). Then the canvas and the line are touched so open conversations redraw both.

## Tests?
- `tests/workspace_canvas_binding_lines_test.php`: the codes, the names, the words, the refs read back, when Undo applies, the hooks. Not run on a database or in a browser (#9333).
