---
name: workspace-composer-later
description: Workspace writing-box tools (toolbar, lists, headings, highlight, slash inserts, smart paste, saved replies, AI rewrite): the owner's answers that still bind and what is left for later
---

## Where is each tool written down?
- One engine, `assets/js/workspace_editor.js` (`PGWsEditor`); its toolbar `workspace-editor-toolbar.md`. Lists and quotes `workspace-text-lists.md`, headings `workspace-text-headings.md`, highlight `workspace-text-highlight.md`, autoformat `workspace-editor-autoformat.md`, link and Ctrl+/ `workspace-editor-link-keys.md`, "Aa" `workspace-composer-expanded.md`, notes' `/` `workspace-notes-slash-inserts.md`, smart paste `workspace-editor-smart-paste.md`, draft `workspace-unsent-draft.md`, meter `workspace-message-meter.md`, `:` emoji `workspace-colon-emoji.md`, tag card `workspace-tag-card.md`, checklist to task `workspace-checklist-to-task.md`, saved replies `workspace-saved-replies.md`, AI rewrite `workspace-ai-rewrite.md`.

## How is a new syntax added?
- Drawn by `ws_render_body()` (and its fast-path regex) alone, twin in `inlineNodes()`/`parseBlocks()`/`serializeNode()`, readable in `ws_plain_text()` (push, e-mail, API `text`) and `ws_guest_render()`, styled in `workspace_guest.css` and the print page; API `body`, MCP, search and Markdown export stay raw. Test `tests/workspace_render_test.php`, red first.
- Labels: `lang()` in `ws_editor_js_strings()` or `ws_js_strings()` (screen.php, sent in `#ws-config`), `t()` in JS.

## Which owner answers still bind? (#2420)
- Headings in notes only.
- Enter in the expanded box is a new line, Ctrl+Enter sends.
- Autoformat on, with an off switch per viewer.
- Saved replies personal first; team replies are a later slice (schema #3075 keeps `scope`, only the right column is left).
- Dictation in the box: not now; recordings and transcripts are built (`workspace-voice-recording.md`).

## What is left for later?
- Callouts, toggles, mermaid, find/replace in the box; team saved replies. No GIF, no video.
