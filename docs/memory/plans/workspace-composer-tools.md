---
name: workspace-composer-tools
description: Plan (2026-10-23, 1 built, answers open): one toolbar for all Workspace text boxes; lists, quotes, headings, slash inserts, smart paste, record cards, saved replies
---

## What is asked? (owner, 2026-10-23)
- More advanced tools in the Workspace text boxes, in notes and the channel composer alike.

## What is there today?
- One engine, `assets/js/workspace_editor.js` (`PGWsEditor`): selection bar, shortcuts, own undo, `pastePlain()`, table, checklist, code (also on ```), calc windows.
- Slice 1 built: `PGWsEditor.toolbar()` draws the rows of `composer()` (now with B/I/S, undo, redo), the notes `toolbar()` and `richField().tools()`; `format()` makes every mark on the nodes, no `execCommand` (docs/memory/workspace/workspace-editor-toolbar.md).
- `ws_render_body()` (`includes/workspace/render.php`, escape first) draws bold, italic, strike, code, links, tables, checklists, fences, `::: Title`; headings, lists, quotes, `---`, images stay raw. `ws_plain_text()` folds lines into one; guest pages also use `ws_guest_render()`. The import (`ws_note_import_convert()`) makes headings and `==x==` bold.

## What is proposed? (not approved yet)
- New tools go into `PGWsEditor.toolbar()` as item names and into `format()` / `applyMark()`. Vanilla JS, then `php tools/build_js.php`.
- New syntax: drawn by `ws_render_body()` (and its fast-path regex) alone, twin in `inlineNodes()`/`parseBlocks()`/`serializeNode()`, readable in `ws_plain_text()` (push, e-mail, API `text`) and `ws_guest_render()`, styled in `workspace_guest.css` and the print page too; API `body`, MCP, search, Markdown export stay raw. Test `tests/workspace_render_test.php` (proposed), red first.
- Labels: `lang()` in `ws_editor_js_strings()` (screen.php, sent in `#ws-config`), `t()` in JS, keys in `includes/local/tr.json`.
- Saved replies live here, not in the CRM seams plan (proposed).

## Slices
1. Shared toolbar, no schema: factory, `format()`; the composer gains undo/redo and B/I/S; notes drop `execCommand`. Built.
2. Lists and quotes, no schema: `- `, `1. ` (one level, Tab, Enter continues, Shift+Enter in the composer), `> `.
3. Headings `#`-`###` and `---` after question 1, no schema; the import keeps headings.
4. Highlight `==x==` (import keeps it; not read by `ws_calc_inline()`), autoformat (one Ctrl+Z undoes, IME guard, off switch), no schema.
5. Link Text field, Ctrl+/ shortcut sheet, "Aa" expanded composer after question 2, no schema.
6. Slash inserts, no schema: in notes `/` lists inserts; channel commands unchanged.
7. Smart paste, no schema: HTML mapped to the subset (DOMParser, never inserted), a sheet range to `openTable()`, a URL over words links them; a note's pasted image to `attach()`.
8. Small aids, no schema, a PR each: unsent draft per person and channel (localStorage, try/catch), meter near `WS_MESSAGE_MAX`, `:` emoji.
9. Objects, no schema: a lone tag as a card per reader (like `ws_form_cards_html()`); checklist item to task (`ws_command_parse_task()`).
10. Saved replies with `{customer}`, `{order}`, schema, ask first: `;` picker, API with dry run; team ones need a right column (five ladder files, `pg_user_has_ws_columns()`).
11. Rewrite with AI, no schema: a `pg_ai_write_targets()` target (proposed), plans/ai-write-buttons.md; tokens kept.
12. Later: callouts, toggles, dictation, mermaid, find/replace; not GIFs or video.

## Open questions (recommendation)
1. Headings in messages: (a) notes only, (b) both → a; answers question 1 of plans/notes-import-export-sync.md.
2. Enter in the expanded box: (a) new line, Ctrl+Enter sends, (b) Enter sends → a.
3. Autoformat: (a) on, off switch per viewer, (b) off → a.
4. Saved replies: (a) personal and team, (b) personal first → b, then a.
5. Dictation (Chrome sends audio to Google): (a) offer, privacy line, (b) not now → owner.

## Where to start?
- Slice 2 with the render test; a list or quote button is a new `TOOLS` entry in workspace_editor.js.
