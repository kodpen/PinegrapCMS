---
name: workspace-flows-box-form
description: PGWsFlow.boxForm() and its siblings in workspace_flow.js: a box's canvas settings drawn in another screen (the quick setup's step setting) with its own state, safe beside a mounted canvas
---

## What is it?
- `window.PGWsFlow.boxForm(host, { graph, node, flows?, boot?, onChange })` draws one box's settings (`nodeForm(node, true)`) into host; `onChange(node)` after each change. Returns `{ node, redraw(), destroy() }`. Added for the quick setup's step setting (QS3 #9278), so a field is described once.
- Siblings: `boxKind(kind)` (name, group, first outcome), `boxConfig(kind, graph)` (a new box's settings as the palette gives them, `newConfig()`), `boxOffered(kind)` (the palette offers it: `paletteItems()`).

## Why is it safe beside a canvas?
- The field code reads the file's module variables `state`, `ui`, `K`, `CFG`, `FL`, `BOOT`, `t`, `el`. A form has its own of each; `formScope()` swaps them in while the form is drawn and restores them in `finally`.
- Listeners added while the form is drawn (`EventTarget.prototype.addEventListener` patched only then) run in the form's scope too, so a change typed later still reads the form's graph.
- The form's `state.form` makes `drawSettings()` redraw the form and `changed()` call onChange: nothing saves, no undo step, the mounted canvas's draft is untouched.

## What does a form not draw?
- settingsOnly: no try field (its runs list and box try answer later, outside the scope), no connections, no Remove; start boxes are not offered, so no web address field.
- `state.flow` is `{ id: 0, manage: true }`: a role-3 member's change box shows its ask fields as on their own flow; `state.flows` the editor's list unless given.

## What was checked?
- A harness page with the real canvas script, a recipe's three steps: the e-mail's recipient switched (the form drawn again), the title written into the step, Add value listing the Read a record box and the earlier steps. A unit test cannot reach the swap (no DOM in the Node tests).
