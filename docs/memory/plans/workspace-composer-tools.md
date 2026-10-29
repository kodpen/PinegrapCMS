---
name: workspace-composer-tools
description: Plan (2026-10-23; 1-9, 10a and 11 done, 10b left): one toolbar for Workspace text boxes; lists, quotes, headings, highlight, slash inserts, smart paste, saved replies
---

## What is asked? (owner, 2026-10-23)
- More advanced tools in the Workspace text boxes, in notes and the channel composer alike.

## What is there today?
- One engine, `assets/js/workspace_editor.js` (`PGWsEditor`): selection bar, shortcuts, own undo, `paste()` (smart paste), table, checklist, code (also on ```), calc windows.
- Slice 1 (#1554): `PGWsEditor.toolbar()` draws the rows of `composer()`, the notes `toolbar()` and `richField().tools()`; `format()` makes every mark on the nodes, no `execCommand` (docs/memory/workspace/workspace-editor-toolbar.md).
- `ws_render_body()` (`includes/workspace/render.php`, escape first) draws marks, links, tables, checklists, fences, `::: Title`, lists and quotes; in notes also headings and `---` (workspace/workspace-text-headings.md); images stay raw. `ws_plain_text()` folds lines into one; guest pages also use `ws_guest_render()`.

## What is proposed? (not approved yet)
- New tools: item names in `PGWsEditor.toolbar()`, then `format()` / `applyMark()`; `php tools/build_js.php`.
- New syntax: drawn by `ws_render_body()` (and its fast-path regex) alone, twin in `inlineNodes()`/`parseBlocks()`/`serializeNode()`, readable in `ws_plain_text()` (push, e-mail, API `text`) and `ws_guest_render()`, styled in `workspace_guest.css` and the print page too; API `body`, MCP, search, Markdown export stay raw. Test `tests/workspace_render_test.php`, red first.
- Labels: `lang()` in `ws_editor_js_strings()` (screen.php, sent in `#ws-config`), `t()` in JS, keys added with `php tools/tr_add.php` (shards in `includes/local/tr/`).
- Saved replies live here, not the CRM plan.

## Slices
1. Shared toolbar, no schema: `format()`, composer undo/redo and B/I/S. PR #1554.
2. Lists and quotes, done: #1725, #1897 (workspace/workspace-text-lists.md, -editor-lists.md).
3. Headings and `---` in notes only, done: #2649 (workspace/workspace-text-headings.md).
4. Highlight `==x==`, autoformat. Done: highlight #1972, #1999 (workspace-text-highlight.md), autoformat #2590 (workspace-editor-autoformat.md).
5. Link Text field, Ctrl+/ sheet, done: #2101 (workspace/workspace-editor-link-keys.md); "Aa" expanded composer, done: #2692 (workspace/workspace-composer-expanded.md); no schema.
6. Slash inserts in notes, done: #2009 (workspace/workspace-notes-slash-inserts.md).
7. Smart paste, done: #2018 (workspace/workspace-editor-smart-paste.md).
8. Small aids, done: unsent draft #2034 (workspace/workspace-unsent-draft.md), meter near `WS_MESSAGE_MAX` #2073 (workspace/workspace-message-meter.md), `:` emoji #2115 (workspace/workspace-colon-emoji.md).
9. Objects, done. 9a lone tag as a card: #2150 (workspace/workspace-tag-card.md). 9b checklist item to task: #2158 (workspace/workspace-checklist-to-task.md).
10. Saved replies with `{customer}`, `{order}`, schema decided in #3075 (b); team ones later need a right column only (five ladder files, `pg_user_has_ws_columns()`).
   - 10a: table, store, API/MCP. Done: #PR10A (workspace/workspace-saved-replies.md).
   - 10b: composer `;` picker (fills `{customer}`/`{order}` via `ws_customer_summary()`), panel list to manage replies.
11. Rewrite with AI, done: #2141, target `ws_message` (workspace/workspace-ai-rewrite.md).
12. Later: callouts, toggles, dictation, mermaid, find/replace; no GIF, video.

## Which questions are answered? (#2420)
1. Headings in notes only (a); answers question 1 of plans/notes-import-export-sync.md.
2. Enter in the expanded box is a new line, Ctrl+Enter sends (a).
3. Autoformat on, with an off switch per viewer (a).
4. Saved replies: personal first (b), team as a later slice.
5. Dictation: not now (b); recordings and transcripts are built (workspace/workspace-voice-recording.md).

## Where to start?
- Steps 1-9, 10a and 11 are done. Left: 10b (the `;` picker and the panel list, no schema); 12 is "later".
