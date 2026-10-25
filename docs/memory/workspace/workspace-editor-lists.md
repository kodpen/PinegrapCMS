---
name: workspace-editor-lists
description: Lists and quotes in Workspace texts ("- ", "1. ", "> "): which lines, how the server draws and reads them, the writing box's lists, keys, buttons, undo, Chrome traps
---

## Which lines are items?
- `ws_list_line()` (render.php; twin `listLine()` in workspace_editor.js): "- " or "* " (bulleted), a number and ". " (numbered), "> " or a lone ">" (quote); not a checklist item, a rule (`* * *`), ">x" or a leading zero ("01." read as 1 was saved back as "1.").
- `ws_text_blocks()` (twin `parseBlocks()`): one level. A numbered list starts only at "1. " and goes on with any number; another number alone ("3. in the race") stays text (an `<ol>` has no `start`). Empty lines between two items keep one list, not in a quote. Lines of ">" with no words stay text.

## How are they drawn and read?
- `ws_render_body()` (messages, notes, task fields, print page) and `ws_guest_render()`: `<ul>`, `<ol>`, `<li>`, `<blockquote>`, no attribute (`ws_text_blocks_html()`), words escaped first; a "\n" between items, blocks and runs, so strip_tags() and textContent (reply quotes, the guest's parent line) keep words apart. The fast-path regex knows the marks.
- `ws_plain_text()` (API and MCP `text`, assistants): running text folds, each item or quoted line keeps a line ("- item", "1. item" numbered as seen, "> line"). `ws_plain_excerpt()` folds all into one line.
- Approval guest mail: `ws_html_blocks_text()` turns them back into lines; a `<br>` is one break.
- "Copy text", "Select text": `readableText()` writes "1. ", "- ", "> " back; innerText has no list markers.
- Stored text, API `body`, search, Markdown export stay raw.

## How does the writing box make them?
- `<ul>`, `<ol>`, `<blockquote>` straight in the box, items `<li>` (a quote's lines `<div>`), an empty item keeps a `<br>`. `listText()` writes an item a line, numbered from 1; empty items go.
- "- ", "* ", "1. ", "> " typed at a line's start (`listCheck()`: insertText only, not in code or an item) is a step after the typing: one Ctrl+Z gives the characters back.
- Buttons `bullets numbers quote` → `format()` → `toggleList()`: off when every touched line is an item of that kind, else each made one; `aria-pressed` from `formatState()`. The composer's group `ws-composer-lists` hides under a 41rem row.
- Enter in an item: the next item (`itemEnter()`), on an empty one the list ends; Shift+Enter in the composer. Backspace at an item's start and Shift+Tab take it out; Tab is not taken. A paste in an item: a line an item. A card goes after the list. A list's first line is no slash command (`inListItem()`).
- Lines are runs of the box's children between breaks (`isolateBreaks()`); `holdSelection()` keeps the selection on moved nodes; `plainOf()`, `placeOf()` count a line around a block alike. No execCommand.

## What did Chrome get wrong?
- A box emptied after showing a list took no typing: `tidy()` calls `freshLayout()`.
- insertHTML took a chip's `<span>` apart in an `<li>` (token lost, nobody told) and moved a chip or link out of a quote's line: `replaceTyped()`, `insertLink()` use `insertAtSelection()`.
- A caret at the box's end after a list types into the last item, yet Enter saw no item and typed a raw "\n": `caretToEnd()` puts it in the item; `listEnter()`, the list buttons and `formatState()` read it there (`caretAfterList()`).

## How was it checked?
- tests/workspace_render_test.php; tests/workspace_editor_lists_test.php (Node twins against PHP, wiring). Headless Chromium on workspace_editor.js: typing, Enter, undo, buttons, paste, picks in items and quotes, copy. Not the whole screen, Safari, Firefox, a phone or a running site.
