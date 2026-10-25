---
name: workspace-editor-lists
description: Lists and quotes in Workspace texts ("- ", "1. ", "> "): which lines, how the server draws and reads them, the writing box's lists, keys, buttons, undo, Chrome traps
---

## Which lines are items?
- `ws_list_line()` (render.php; twin `listLine()`): "- ", "* ", a number and ". ", "> " or a lone ">"; not a checklist item, a rule, ">x" or a leading zero ("01.").
- `ws_text_blocks()` (twin `parseBlocks()`): one level. A numbered list starts only at "1. ", then any number; another number alone stays text (no `start`). Empty lines between two items keep one list, not in a quote. ">" with no words stays text. A code block's lines are never items (`ws_code_fence_lines()`).

## How are they drawn and read?
- `ws_render_body()`, `ws_guest_render()`: `<ul> <ol> <li> <blockquote>`, no attribute (`ws_text_blocks_html()`), escaped first; a "\n" between items and blocks keeps words apart for strip_tags().
- `ws_plain_text()` (API, MCP, assistants): running text and code fold, an item or quoted line a line ("1. " as seen). `ws_plain_excerpt()` folds all.
- A sum in an item is worked out from its words everywhere: `ws_calc_plain()` reads `ws_text_block_items()` ("- 10 - 4 =" gave -14 to assistants, 6 on screen).
- Approval mail: `ws_html_blocks_text()`. "Copy text": `readableText()` writes the marks. Stored text, API `body`, search, export stay raw.

## How does the writing box make them?
- `<ul> <ol> <blockquote>` in the box, items `<li>` (a quote's `<div>`), an empty item keeps a `<br>`. `listText()`: an item a line from 1; empty items go.
- "- ", "1. ", "> " typed at a line's start (`listCheck()`) is a step after the typing; if no list can be made the characters go back.
- Buttons `bullets numbers quote` → `toggleList()`: off when every touched line is that kind; `aria-pressed` from `formatState()`. The composer's group hides under a 41rem row.
- Enter in an item: the next item (`itemEnter()`); on an empty last item the list ends (a quote ends anywhere); an empty middle item stays one. Shift+Enter in the composer. A selection goes first (`takeSelection()`): the emptied item goes on, words after it join it.
- Lists of a kind with only empty lines between are joined (`listAfter()`), not while the caret is on them: the readers show one list.
- Backspace at an item's start, Shift+Tab take it out; Tab is not taken. Paste and drop (`insertPlain()`): in an item a line an item, its own mark off (`itemWords()`). Code over several items: a span in each (`codeSpan()`). A list's first line is no slash command.
- Lines are runs of the box's children between breaks (`isolateBreaks()`; a `<div>` line unwrapped by `unwrapLines()`); `holdSelection()` keeps the selection. No execCommand.

## What did Chrome get wrong?
- A box emptied after a list took no typing: `tidy()` calls `freshLayout()`.
- insertHTML took a chip apart in an `<li>` and moved a chip out of a quote's line: `insertAtSelection()`.
- A caret after a final list types into its last item, yet Enter saw none: `caretIntoList()`.
- `deleteContents()` over two items leaves the caret between them; a dropped "\n" made `<div>` lines.

## How was it checked?
- tests/workspace_render_test.php; tests/workspace_editor_lists_test.php (Node twins, wiring). Headless Chromium on workspace.min.js: typing, Enter over selections, undo, buttons, paste, drop, code, picks, copy. Not the whole screen, Safari, Firefox, a phone or a running site.
