---
name: workspace-editor-toolbar
description: One row of tools for every Workspace writing box (PGWsEditor.toolbar()); marks made on the nodes by format(), never execCommand; waiting marks, pressed state, undo steps, labels
---

## What builds the row under a writing box?
- `PGWsEditor.toolbar(editor, items, options)` (`assets/js/workspace_editor.js`). Items in order: tool names (`undo redo bold italic strike code link checklist table calc codeblock`), nodes the caller made (attach, emoji, voice, send), arrays for groups (`options.groupClass`). Options: `className`, `label` (aria-label), `insertLabel`, `scope` (table and calculation windows).
- Users: `composer()` (`ws-composer-tools`; B I S and undo redo as two `ws-composer-format` groups after `/`), the notes `toolbar()` (`ws-nb-tools`, `ws-nb-tool-group`), `richField().tools()` (`ws-note-actions`: @ # emoji, checklist). The templates form (`workspace_templates.js`) still draws its own row.
- The row is `role="toolbar"` with a name; arrows, Home and End move between its buttons (also ones the caller appends later); mousedown is prevented on the row so the text keeps focus and selection; undo and redo are disabled while there is nothing to take back; marks carry `aria-pressed`.

## How is a mark made?
- `editor.format(name)`: `restore()`, then `applyMark()` on the nodes: `<b> <i> <s> <code> <a href>`; `<strong> <em> <del>` read as the same. Never `document.execCommand()` (differs per browser, keeps its own undo, deprecated). `command()` is left only for typing-like inserts: text, line breaks, chips, cards.
- All words of the selection marked: the mark comes off (`liftOut()` cuts it around each text node), else it goes on; chips, cards and `[data-ws-skip]` results stay as they are; neighbouring same marks are joined; the selection stays.
- A caret with nothing selected: B, I, S wait (`surface.wsMarks`) for the next typed letters (`beforeinput` insertText) and are forgotten when the caret moves. IME composition does not take them.
- Code puts the selection in one `<code>`, or the code around the caret back to text. Link opens the tip; a selection is linked by `linkRange()`.
- Ctrl/Cmd+B, I, Shift+X, E, K, the bar over a selection, the browser's `formatBold/Italic/StrikeThrough` and the table and checklist cells all go through `formatSurface()`.

## How do undo and the pressed state follow?
- `change(apply)`: typing before it becomes a step, the change another; `announce()` sends `input` without a step, so a note saves and the composer grows.
- `watch(fn)` / `notify()`: on history changes and on `selectionchange` once a frame; `formatState()` reads the selection, or the caret the box kept when it was left.

## Where are the labels?
- `tool_*` keys in `ws_editor_js_strings()` (`includes/workspace/screen.php`). Reused tr.json keys "Undo (Ctrl+Z)", "Redo (Ctrl+Y)", "Bold (Ctrl+B)", "Italic (Ctrl+I)", "Checklist", "Table", "Calculation", "Code block"; added "Strikethrough (Ctrl+Shift+X)", "Code (Ctrl+E)", "Link (Ctrl+K)", "Text tools", "Message tools", "Note tools". On a Mac the title shows ⌘ and `aria-keyshortcuts` says Meta. The notes' own `notes_bold` ... `notes_redo` keys are gone.

## What does it look like?
- Ghost icon buttons as before. The composer's two groups are hidden when its row is under 32rem (container query on `.ws-composer-tools`) and below 768px, so phones and the discussion panel keep the old row; the hint ends in an ellipsis instead of wrapping. Pressed is the accent tint of the selection bar; a disabled ghost button has no frame (Bootstrap left its border the text colour).

## How was it checked?
- Headless Chromium on `workspace.min.js` with a stubbed `api.php`: three stub boxes, the real channel composer, the notes screen (`?new=1`) and the approval form's box. Marks on and off, pressed, focus kept, undo and redo of a mark and of a typed word, Space on a focused button, shortcuts, waiting marks, Ctrl+K link, a table cell, Enter sends (`ws_send`), the note autosaves, no `execCommand` call. Not on Safari, Firefox or a running site.
