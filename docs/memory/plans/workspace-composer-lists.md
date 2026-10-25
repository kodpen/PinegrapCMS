---
name: workspace-composer-lists
description: Step 2 of plans/workspace-composer-tools.md (lists and quotes), not started on development: an unmerged branch, its 9 open review findings, the split into 2a (server) and 2b (editor)
---

## What is there?
- Branch `feature/ws-composer-lists` at `97f11499`, never opened as a PR, based on `development` at `9a4b0edc` (2026-10-25) and not merged with it since: 23 files, about 2,965 added lines, too big for one PR. Nobody holds it; use it as a reference, not as a base.
- Server: `ws_list_line()`, `ws_text_blocks()`, `ws_text_blocks_html()`, `ws_html_blocks_text()`, `ws_plain_text()` / `ws_plain_excerpt()` in includes/workspace/render.php; guest and approval pages.
- Editor (assets/js/workspace_editor.js): autoformat, Enter / Shift+Enter, toolbar tools, paste, `insertAtSelection()` in place of `execCommand('insertHTML')`.
- CSS, tests/workspace_render_test.php, tests/workspace_editor_lists_test.php, docs/memory/workspace/workspace-editor-lists.md.

## Which findings are open? (third review, confirmed)
- A person or record picked inside a bold, italic, strike or code mark lands inside it: `insertAtSelection()` inserts at the start of the deleted range, the mark characters show as text, a mention inside code is lost. A regression of mentions and links, outside lists.
- `ws_text_blocks()`: a numbered list broken by another line draws only its first item as a list; old messages with sub-bullets or a paragraph between items look worse than on `development`.
- The numbers the author wrote are replaced once a list starts at "1.": on screen, in `ws_plain_text()`, in the approval mail.
- Editing an old message or note rewrites list lines nobody touched: indentation dropped, `* ` becomes `- `.
- The styles miss `.ws-fwd-body`, `.ws-nb-msg-card-html` and the other places `ws_render_body()` HTML is drawn.
- Minor: select all and delete in a box that starts with a list keeps an empty bullet; `ws_plain_text()` groups lists after stripping marks; a Turkish ordinal at line start ("1. Lig") draws as a one-item list; `ws_plain_text()` returns several lines into stored bodies (poll result decision).

## How is it split?
- 2a, server rendering only, about 500 product lines: lists and quotes in render.php only where they cannot change how old messages look, `ws_plain_text()` keeping the author's numbers and one line where bodies are stored, CSS for every place the HTML is drawn. Red tests first for the findings above that touch the server.
- 2b, editor support, after 2a merges: Enter, Tab, autoformat, toolbar items; a pick inside a mark keeps the mark's characters out (the first finding).

## Where to start?
- 2a. Read workspace/workspace-editor-toolbar.md and plans/workspace-composer-tools.md; diff the branch's render.php against `development` for the parsing, not its editor part.
