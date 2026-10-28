---
name: workspace-text-headings
description: Headings "#" to "###" and "---" rules in Workspace notes only (#2649): server rule and fast path, plain text, editor twins, tools, Enter/Backspace, "# " autoformat, the import
---

## Which lines are a heading or a rule, and where?
- Notes only (owner decision in #2420, answer 1a): a message, the composer, the guest page and a task's text keep `#` and `---` as words, since `#` there was always text.
- `ws_heading_line()` (includes/workspace/render.php): `# `, `## `, `### ` at the very start of the line and words after it (`/^(#{1,3})[ \t]+(\S.*)$/u`). `#tag`, `#### x`, ` # x`, `\# x` are none. `ws_rule_line()`: three dashes or more alone on a line. `ws_heading_lines()` skips lines of code.

## How is it drawn?
- `ws_render_body(..., $note = true)`: `#` → `<h2 class="ws-heading h4">`, `##` → `<h3 … h5>`, `###` → `<h4 … h6>` (Bootstrap's size classes keep them below the page title; no rule in backend.src.css), a rule `<hr class="ws-rule">`. A heading or rule flushes the running text, so a list after it is still a list. The fast path is left only when a note holds one.
- Callers with `$note`: `ws_note_body_view()`, the note card in a channel (notes.php), the note's block previews (`ws_calc` blocks, actions.php), the print page (workspace_notes_export.php, own style rules).
- `ws_plain_text(..., $note = true)` / `ws_plain_line()`: a heading is a line of its own without its signs, a rule none. Used by the note title from the first line, the excerpt and the API note `text`. API `body`, MCP, search and the Markdown export stay raw.

## How does the writing box follow?
- `options.headings` (set by the note's `richField()` in workspace.js) makes `parseBlocks(markup, headings)` read `headingLine()` / `ruleLine()`, the twins (tests/workspace_editor_headings_test.php holds them against the PHP).
- In the box a heading is `h2/h3/h4.ws-heading`, a rule `hr.ws-rule`, each followed by a `<br>` that `serializeNode()` skips, as after a card; `boxLines()` sees them as fixed lines, so lists never take them. `isEmpty()` counts them.
- Tools `heading1 heading2 heading3 rule` (labels `tool_heading1`…`tool_rule`), on the note's row only: `editor.heading(level)` → `toggleHeading()` (off when every line is that level already), `editor.rule()` → `insertRule()` (below the caret's line, or in place of an empty one). `formatState()` reports `heading1`–`3` for `aria-pressed`.
- Enter in a heading puts the words after the caret on a plain line below (`headingEnter()`); Backspace at its start makes it a line again (`headingKey()`).
- Autoformat: `# `, `## `, `### ` typed alone on a line (`autoHeading()`), through the same `autoformat()` as lists: one undo, the IME/paste guards, the per-viewer off switch (workspace-editor-autoformat.md). Not in a list item or a heading.
- Not in the "/" list (`SLASH`) and smart paste still makes HTML headings bold lines.

## What does the import do?
- `ws_note_import_convert()` (notes_io.php) keeps `#`–`###`, writes `####` and below as `###`, a line underlined with `=` as `#`, with `-` as `##`, drops a closing run of `#`. `\# words` keeps its backslash, else the note would draw a heading.

## How was it checked?
- tests/workspace_render_test.php, workspace_notes_io_test.php, workspace_editor_headings_test.php; headless Chromium on workspace_editor.js alone: round trips, tools, Enter, Backspace, rule, `## ` autoformat, a message box leaving `# ` alone. Not the whole screen, Safari, Firefox or a phone.
