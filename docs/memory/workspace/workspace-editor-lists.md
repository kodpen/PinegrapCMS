---
name: workspace-editor-lists
description: Lists and quotes in the Workspace writing box (#1897): twins of the server's rules, lines written back as stored, keys, the three tools, a pick inside a mark, select all and delete
---

## How does the box read a stored text?
- `listLine()` / `textBlocks()` (assets/js/workspace_editor.js) are the twins of `ws_list_line()` / `ws_text_blocks()` (workspace-text-lists.md); `parseBlocks()` groups its text lines with them, a card's lines ending a paragraph. tests/workspace_editor_lists_test.php runs them under Node against the PHP ones.
- A list is `<ul class="ws-list">`, `<ol class="ws-list">` or `<blockquote class="ws-quote">` straight in the box, one level; an item `<li>` (a quote's line a `<div>`). The backend.src.css rules for drawn texts style them in the box too.

## How is a list written back?
- `listMarkup()`: each item keeps the mark it was read with (`data-ws-mark`: indent, `-`/`*`/number, spacing), so a line nobody touched is written as stored ("* " stays "* ", indentation stays). An item the box made takes the mark of the item before it, indent included: the server sees one list only at one indent.
- Numbers are counted again from 1 (an untouched list already runs 1, 2, 3); empty items are left out; a line break in an item makes an item; a quote keeps empty lines between words.
- Words right after a list get an empty line before them (`wordsAfter()`): the server draws a list only where nothing follows it in its paragraph. A parsed list never has words after it, so stored texts do not change.

## Which keys and tools?
- Enter in an item: a new item with the words after the caret; on an empty item the list ends there (items below become a list of their own). In the composer Enter sends, so Shift+Enter continues.
- Shift+Tab, and Backspace at an item's start, take the item out (`liftItem()`). Tab puts a line straight after a list back into it; elsewhere Tab moves the focus (no level below an item).
- Tools `bullets numbers quote` (`TOOLS`, labels `tool_bullets`, `tool_numbers`, `tool_quote` in `ws_editor_js_strings()`): `editor.list()` → `toggleList()` over the selection's lines; off when all are that kind already. `formatState()` reports them for `aria-pressed`. In the composer (third group, hidden under a 41rem row), the note row and `richField().tools(withList)`.
- `inListItem()`: a list's first item starting with "/" opens no command picker in the composer.
- No `execCommand`: lists are built on the nodes; the selection is put back by its nodes (`holdSelection()`).

## Which bugs were fixed with it?
- A person or record picked inside bold, italic, strike, code or a link went into the mark (in code the chip was lost, in bold its `**` showed as characters): `replaceTyped()` splits the mark at the caret (`outOfMarks()`) and inserts the chip between the halves, not with insertHTML.
- Select all and delete in a box with a list left an empty bullet: a delete over the whole box empties it (`wholeBox()`). An empty item made on purpose (a tool on an empty box) stays: `isEmpty()` counts lists.
- A card inserted in an item, or right after a list that ends the box, is put in below the list as nodes (`insertBlock()`): insertHTML next to a list drew it into the last item, saved as `- - [ ] one` (#1934). `<code>` is not made over two items.
- Letters typed with a mark waiting right after a list that ends the box go into its last item, as letters without one do (`typeMarked()` retargets with `boundaryItem()`).

## What is not done?
- Typed autoformat ("- " at a line's start making a list): workspace-editor-autoformat.md.

## How was it checked?
- Node twins and wiring in the test; headless Chromium on the editor alone: round trips, Enter, Shift+Enter, Tab, Shift+Tab, Backspace, tools and pressed state, undo, select all and delete, chips in bold and code, a card and marked letters after a list. Not the whole screen, Safari, Firefox, a phone or a running site.
