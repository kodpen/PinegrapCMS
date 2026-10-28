---
name: workspace-editor-autoformat
description: Autoformat while typing in a Workspace writing box: ==x== to a highlight, "- " "* " "1. " "> " at a line's start to a list or quote; one undo, IME and paste guards, the per-viewer off switch
---

## What does the box format as it is typed?
- `Editor.autoformat(event)` (assets/js/workspace_editor.js), asked by the box's `input` listener: every box made by `PGWsEditor.create()` (composer, notes, `richField()` fields, the templates form); not table or checklist cells.
- A typed `=` closing a pair: `autoMark(line, next)` reads the line's pairs from its start with the highlight regex of `inlineNodes()` and answers only when the last one ends at the caret, so only a pair the server draws becomes a `<mark>` ("==a==b==": the first pair is the highlight, nothing is made). A `=` right after the caret makes no pair. The pair must sit in the caret's own text node, outside a highlight.
- A typed space after `- `, `* `, `1. ` or `> ` alone on the line: `autoList(line)` gives bullet, number or quote; the signs are deleted and `toggleList()` makes the list as the tools do. Not an indent or another number (the server draws those as text), not inside an item already. Words after the caret on that line go into the item.
- Twins and the server check: tests/workspace_editor_autoformat_test.php holds `autoMark()` against `ws_format_text()`.

## How does undo work?
- `change()`: the typing up to the trigger (`==x==`, `- `) is recorded as a step, the format is another, so one Ctrl+Z gives back the typed text; Ctrl+Y formats again.
- After a highlight the caret waits with highlight off (`toggleWaiting()`), so the next letters go after the mark.

## When does it not run?
- Only `inputType` insertText: not a paste, drop, or an IME's commit (insertCompositionText); not while `event.isComposing` or `wsComposing` (nodes are never changed under a composition); not where `caretBlocked()` (code, link, cell, card); not with marks waiting (those letters come through `typeMarked()` without an inputType).

## Where is the off switch?
- In the Ctrl+/ shortcut sheet under an Editor's keys: "Format as you type" (`keys_autoformat`, help `keys_autoformat_help`, Ctrl shown as ⌘ on a Mac by `keyLabel()`). `autoformatSet()` keeps it in localStorage `ws-autoformat` = `off`, as the other per-viewer Workspace view choices (closed groups, sidebar width); per browser, no schema. Storage refused: the choice lasts the page.

## Which bug was fixed with it?
- A space typed with a mark waiting off (Ctrl+B at a bold word's end, or after an autoformatted highlight) was lost and the next letters went into the mark: a plain space at its node's edge collapses. `typeMarked()` now types an edge space as U+00A0, which `serializeNode()` writes as a space.

## How was it checked?
- Node tests above; headless Chromium on workspace_editor.js alone: highlight, undo/redo, text after it, lists from an empty box and mid-text, `2. ` and `**- ` left alone, code left alone, the switch and its stored value. Not Safari, Firefox, a phone IME or a running site.
