---
name: workspace-composer-tools
description: Plan (2026-10-23; 1, 2, 4, 6, 7, 8, 9, 11 done, 5 partly; answers open): one toolbar for Workspace text boxes; lists, quotes, headings, highlight, slash inserts, smart paste, saved replies
---

## What is asked? (owner, 2026-10-23)
- More advanced tools in the Workspace text boxes, in notes and the channel composer alike.

## What is there today?
- One engine, `assets/js/workspace_editor.js` (`PGWsEditor`): selection bar, shortcuts, own undo, `paste()` (smart paste), table, checklist, code (also on ```), calc windows.
- Slice 1 (#1554): `PGWsEditor.toolbar()` draws the rows of `composer()`, the notes `toolbar()` and `richField().tools()`; `format()` makes every mark on the nodes, no `execCommand` (docs/memory/workspace/workspace-editor-toolbar.md).
- `ws_render_body()` (`includes/workspace/render.php`, escape first) draws marks, links, tables, checklists, fences, `::: Title`, lists and quotes; headings, `---`, images stay raw. `ws_plain_text()` folds lines into one; guest pages also use `ws_guest_render()`. The import (`ws_note_import_convert()`) makes headings bold.

## What is proposed? (not approved yet)
- New tools: item names in `PGWsEditor.toolbar()`, then `format()` / `applyMark()`; `php tools/build_js.php`.
- New syntax: drawn by `ws_render_body()` (and its fast-path regex) alone, twin in `inlineNodes()`/`parseBlocks()`/`serializeNode()`, readable in `ws_plain_text()` (push, e-mail, API `text`) and `ws_guest_render()`, styled in `workspace_guest.css` and the print page too; API `body`, MCP, search, Markdown export stay raw. Test `tests/workspace_render_test.php`, red first.
- Labels: `lang()` in `ws_editor_js_strings()` (screen.php, sent in `#ws-config`), `t()` in JS, keys added with `php tools/tr_add.php` (shards in `includes/local/tr/`).
- Saved replies live here, not the CRM plan.

## Slices
1. Shared toolbar, no schema: `format()`, composer undo/redo and B/I/S. PR #1554.
2. Lists and quotes, no schema, done: #1725, #1897 (workspace/workspace-text-lists.md, -editor-lists.md); autoformat in 4.
3. Headings `#`-`###` and `---` after question 1, no schema; the import keeps headings.
4. Highlight `==x==`, autoformat (one Ctrl+Z undo, IME guard, off switch), no schema. Done: highlight #1972, #1999 (workspace-text-highlight.md), autoformat #2590 (workspace-editor-autoformat.md).
5. Link Text field, Ctrl+/ sheet, done: #2101 (workspace/workspace-editor-link-keys.md); "Aa" expanded composer after question 2; no schema.
6. Slash inserts in notes, done: #2009 (workspace/workspace-notes-slash-inserts.md).
7. Smart paste, no schema, done: #2018 (workspace/workspace-editor-smart-paste.md).
8. Small aids, no schema, done: unsent draft #2034 (workspace/workspace-unsent-draft.md), meter near `WS_MESSAGE_MAX` #2073 (workspace/workspace-message-meter.md), `:` emoji #2115 (workspace/workspace-colon-emoji.md).
9. Objects, no schema, done. 9a lone tag as a card: #2150 (workspace/workspace-tag-card.md). 9b checklist item to task: #2158 (workspace/workspace-checklist-to-task.md).
10. Saved replies with `{customer}`, `{order}`, schema, ask first: `;` picker, API with dry run; team ones need a right column (five ladder files, `pg_user_has_ws_columns()`).
11. Rewrite with AI, no schema, done: #2141, target `ws_message` (workspace/workspace-ai-rewrite.md).
12. Later: callouts, toggles, dictation, mermaid, find/replace; no GIF, video.

## Open questions (recommendation)
1. Headings in messages: (a) notes only, (b) both → a; answers question 1 of plans/notes-import-export-sync.md.
2. Enter in the expanded box: (a) new line, Ctrl+Enter sends, (b) Enter sends → a.
3. Autoformat: (a) on, off switch per viewer, (b) off → a.
4. Saved replies: (a) personal and team, (b) personal first → b, then a.
5. Dictation (Chrome sends audio to Google): (a) offer, privacy line, (b) not now → owner.

## Where to start?
- Steps needing no answer are done. Left: 3 (question 1), the "Aa" box of 5 (question 2), autoformat of 4 (`==x==`, "- " at a line's start; question 3), 10 (question 4, schema).
