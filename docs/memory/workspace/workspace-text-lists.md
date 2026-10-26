---
name: workspace-text-lists
description: Lists and quotes in Workspace texts drawn by the server (#1725): which shapes become a list, which stay text so old messages look the same, plain text, guest page, approval mail
---

## Which lines become a list or a quote?
- `ws_list_line()` (includes/workspace/render.php) reads one line: `- ` / `* ` bullet, `1.`–`999.` number, `> ` or a lone `>` quote. A checklist item, a rule (`* * *`) and `01.` are none.
- `ws_text_blocks()` groups a paragraph (lines up to an empty one): words may lead in ("Shopping:"), then every line to the paragraph's end must be an item of one kind at one indent. A numbered list needs two items or more numbered 1, 2, 3... A quote needs a line with words.
- Anything else stays text, drawn as before lists existed: sub-items, a line between or after the items, two kinds, numbers that skip or start late, a lone "1. Lig". Texts were stored for years before lists were drawn; a shape that could mean something else must not change look. `tests/workspace_render_test.php` keeps those shapes equal to `ws_render_text()`.

## How is it drawn?
- `ws_text_blocks_html()`: `<ul class="ws-list">`, `<ol class="ws-list">` (no `start`: numbers always run from 1 as written), `<blockquote class="ws-quote">`; words through the same escape-first renderer. `ws_render_body()` keeps its one-piece fast path unless a list or quote is found.
- Classed, so one rule styles every place: backend.src.css, workspace_guest.css, workspace_approval.css, the notes export/print page's own style block.
- The guest page (`ws_guest_render()`) draws the same blocks; lines of code (`ws_code_fence_lines()`) are never items.

## What does plain text read?
- `ws_plain_text()` (API `text`, assistants, e-mail): running text folds to one line as before; each item or quoted line its own line with the author's number. Lists are found before marks come off, so `**- x**` stays words.
- `ws_plain_line()` folds it all to one line: for a stored body (the poll result decision in interact.php), banners, `ws_plain_excerpt()`.
- The approval mail (`ws_approval_guest_details_text()`) turns the blocks back into lines (`ws_html_blocks_text()`) and keeps one line break per break (nl2br's newline used to double them).
- `ws_calc_plain()` works out an item's words (`ws_text_block_items()`): in "- 10 - 4 =" the bullet is no minus sign, as on screen.

## Where does the writing box follow these rules?
- workspace-editor-lists.md (#1897): its twins `listLine()` / `textBlocks()` make the same shapes lists; change both sides together.
