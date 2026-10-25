---
name: workspace-editor-lists
description: Lists and quotes in Workspace texts ("- ", "1. ", "> "): which lines, how the server draws and reads them, the writing box's lists, keys, buttons, undo, a Chrome caret trap
---

## Which lines are items?
- `ws_list_line()` (render.php; twin `listLine()` in workspace_editor.js): "- " or "* " (bulleted), a number and ". " (numbered), "> " or a lone ">" (quote); not a checklist item, a rule (`* * *`) or ">x".
- `ws_text_blocks()` (twin `parseBlocks()`): one level, an indented item is an item. A numbered list starts only at "1. " and goes on with any number; another number alone ("3. in the race") stays text, since an `<ol>` has no `start` and would show 1. Empty lines between two items keep one list (Markdown's loose list), not in a quote.

## How are they drawn and read?
- `ws_render_body()` (channels, threads, notes, task fields, summaries, print page) and `ws_guest_render()`: `<ul>`, `<ol>`, `<li>`, `<blockquote>`, no attribute (`ws_text_blocks_html()`), words escaped first; its fast-path regex knows the marks.
- `ws_plain_text()` (API and MCP `text`, assistants): running text folds as before, each item or quoted line keeps a line ("- item", "1. item" numbered as seen, "> line"). `ws_plain_excerpt()` (push, banners, calendar feed) folds all into one line.
- Approval guest mail: `ws_html_blocks_text()` turns them back into lines; a `<br>` is one break (nl2br's own newline doubled every break before).
- Stored text, API `body`, search, Markdown export stay raw; the notes import already writes "- ", "1. ", "> ".

## How does the writing box make them?
- `<ul>`, `<ol>`, `<blockquote>` straight in the box, items `<li>` (a quote's lines `<div>`), an empty item keeps a `<br>`. `listText()` writes an item a line, numbered from 1; a line break in an item is an item, empty items go.
- "- ", "* ", "1. ", "> " typed at the start of a line (`listCheck()`: insertText only, not in code or an item) is a step after the typing: one Ctrl+Z gives the characters back.
- Buttons `bullets numbers quote` → `format()` → `toggleList()`: off when every touched line is an item of that kind, else each made one; empty lines of a longer selection stay; `aria-pressed` from `formatState()`. The composer's own group `ws-composer-lists` hides under a 41rem row (it needs about 40rem, the marks 33rem); the notes and field rows wrap.
- Enter in an item: the next item (`itemEnter()`); on an empty one the list ends. The composer sends on Enter, so there Shift+Enter (Enter on a touch screen). Backspace at an item's start and Shift+Tab take it out; Tab is not taken (no level below, focus moves on). A pasted text in an item: a line an item. A card goes after the list.
- Lines are runs of the box's children between breaks: `isolateBreaks()` makes a "\n" its own node and lifts breaks out of marks. A moved node loses the live selection, so `holdSelection()` keeps it on text nodes. `plainOf()`, `placeOf()`, `placeCaret()` count a line before and after a block alike. No execCommand.

## What did Chrome get wrong?
- A box that showed a list, then emptied with the absolute `::before` placeholder as its only child, took no typing (no beforeinput). `tidy()` calls `freshLayout()` (display flow-root for a moment) when a box that held a list becomes empty; focus and caret stay.

## How was it checked?
- tests/workspace_render_test.php; tests/workspace_editor_lists_test.php (Node twins against PHP). Headless Chromium on workspace_editor.js: typing, Enter, Shift+Enter, undo, buttons, paste, Tab, deletion across items, row widths. Not the whole Workspace screen, Safari, Firefox, a phone or a running site.
