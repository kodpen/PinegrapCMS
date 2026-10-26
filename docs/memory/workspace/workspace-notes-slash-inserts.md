---
name: workspace-notes-slash-inserts
description: "/" in a note's writing box lists the box's own inserts (lists, quote, checklist, table, calculation, code block, highlight); where it opens, keys, undo, what it never does
---

## Where does "/" open the list?
- Only in a note's box: `startNotes()` (assets/js/workspace.js) passes `slashInserts: { insertLabel }` through `richField()` to `PGWsEditor.create()`. The composer and every other box leave "/" as before; the composer's command picker (workspace-command-registry.md) is untouched.
- `slashInserts(before, blocked, labels)` (assets/js/workspace_editor.js, pure): a "/" at a line's start, after a space or a chip; never in a word (`and/or`, `/var/www`), inside code, a link or a card (`blocked`), nor while a word is composed (IME). `Editor.slashCheck()` runs it on every input.

## What does it list?
- `SLASH`: bullets, numbers, quote, checklist, table, calc, codeblock, highlight, with their `TOOLS` icons and `tool_*` labels (shortcut cut off). No heading or `---`: the server draws neither (plan question 1).
- Filter: a word of the label or the tool's name begins with the typed word, in plain or Turkish lower case ("/tab" → Table, "/işa" → İşaretle). Nothing matching closes the list.

## Keys and undo?
- No row chosen after a bare "/" (Enter still breaks the line); the first once a word is typed. Arrows choose, Enter or Tab inserts, Escape closes and leaves the "/" as typed (not reopened for that slash), a space or a sideways caret closes. "//" with the list open leaves one plain "/".
- `slashPick()` removes the "/word", then acts as the tool's button: lists through `toggleList()` in the same `change()` step (one Ctrl+Z brings "/word" back), highlight waits for the next letters (`applyMark()`), cards open their window (`insertFrom()`), like ``` opens the code window.

## How was it checked?
- tests/workspace_editor_slash_test.php runs `slashInserts()` under Node and holds the wiring. The popup, keys and undo in a browser were not run (Working mode 4: no browser run for this step).
