---
name: workspace-editor-smart-paste
description: Pasting into a Workspace writing box (#2018): HTML mapped to the box's markup and why it is safe, sheet ranges to the table window, a URL over words, a note's picture
---

## What happens on a paste?
- `paste()` (assets/js/workspace_editor.js) on every surface. Plain text, line by line (`pastePlain()`), in a one-line field, a table or checklist cell and after Ctrl/Cmd+Shift+V (`surface.wsPlainPaste`, set on keydown).
- In a writing box `Editor.pasteSmart(text, html)`, in this order: one address alone (`pastedUrl()`, the link rule `PASTE_LINK`) over selected words links them (`linkSelection()` → `linkRange()`, one undo step); a sheet range (`pasteSheet()`) or HTML that is one table alone opens `openTable()` filled (`options.fresh`: the button says Insert), its answer put in by `insertBlock()`; other HTML goes in as the box's nodes (`insertMarkup()` → `markupNodes()`, the same builder as `setMarkup()`); else plain paste.

## How is HTML read, and why is it safe?
- `pasteHtml()`: `DOMParser` 'text/html' builds a detached, inert document (no script runs, nothing loads), never put in the page; `pasteMarkup(body)` walks it and answers stored markup text. Only that text reaches the box, through `parseBlocks()`/`inlineNodes()`: no element, attribute, style or class of the source survives, and nothing on this path uses `innerHTML` or `insertHTML` (the test checks).
- A link keeps only an `http(s)://` or `mailto:` address matching `PASTE_LINK` (no `javascript:`, `data:`, relative or a `)`); otherwise its words stay.
- Dropped whole (`PASTE_DROP`): script, style, template, iframe, object, svg, img, media, form controls, head. Comments go (Word's conditional parts).

## What is mapped?
- Marks: b/strong, i/em, s/del/strike, mark, code/kbd → `** _ ~~ == \``; from styles too: font-weight, font-style, line-through, a background colour (not white or transparent) or Word's mso-highlight. A style wins over its tag (Google Docs' outer `<b style="font-weight:normal">`). A mark whose sign is in its words is left off. Headings become bold lines.
- ul/ol → `- ` / `1. ` items, nested lists flattened into the same list (the box has one level); an item with a checkbox input or `aria-checked` (Google Docs) → `- [ ]`/`- [x]`; Word's `mso-list` paragraphs → items, numbered when the bullet reads like `1.`/`a)`. An empty line is put after a list that words follow.
- blockquote → `> ` lines; pre → a ``` fence (its lines as text if they hold a fence); `white-space:pre` keeps spaces (code from an editor).
- table → the box's table, first row the head, `|` escaped; a table of one column, one row, over 12 columns or 200 rows (the window's limits) stays words. Anything else (underline, sup, fonts) stays its words.

## Sheets, URLs, pictures?
- `pasteSheet()`: two rows at least, every row the same number of tabs, 2–12 columns; a single row or column stays text.
- A URL with nothing selected pastes as before (or as its HTML link).
- Pictures: only a box made with `pasteFiles` takes them, and only when the clipboard holds no words (Word adds a picture of its text). The note passes `attach()` through `richField()` (workspace.js), so a pasted screenshot is attached like a dropped file. The composer's own paste handler still uploads files; other boxes are unchanged.

## How was it checked?
- tests/workspace_editor_paste_test.php runs the pure walk under Node on trees PHP's DOM parses (marks, junk, dangerous attributes and links, Word and Google Docs shapes, lists, tables, sheets, URLs) and holds the wiring. Headless Chromium on the editor alone: HTML, list, table, URL-over-words and Ctrl+Shift+V pastes (PR #2018). Real Word, Google Docs, Excel/Sheets clipboards were not tried.
