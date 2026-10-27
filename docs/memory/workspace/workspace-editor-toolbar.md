---
name: workspace-editor-toolbar
description: One row of tools for every Workspace writing box (PGWsEditor.toolbar()); marks made on the nodes by format(), never execCommand; waiting marks, IME, pressed state, undo, labels
---

## What builds the row under a writing box?
- `PGWsEditor.toolbar(editor, items, options)` (`assets/js/workspace_editor.js`). Items in order: tool names (`undo redo bold italic strike highlight code link bullets numbers quote checklist table calc codeblock`; lists: workspace-editor-lists.md), nodes the caller made (attach, emoji, voice, send), arrays for groups (`options.groupClass`). Options: `className`, `label` (aria-label), `insertLabel`, `scope` (table and calculation windows).
- Users: `composer()` (`ws-composer-tools`; B I S, lists, undo redo as three `ws-composer-format` groups after `/`), the notes `toolbar()` (`ws-nb-tools`), `richField().tools()` (`ws-note-actions`). The templates form (`workspace_templates.js`) draws its own row.
- `role="toolbar"` with a name; arrows, Home, End move between buttons (also ones appended later); mousedown is prevented on the row so the text keeps focus and selection; undo, redo off with nothing to undo; marks carry `aria-pressed`.

## How is a mark made?
- `editor.format(name)`: `restore()`, then `applyMark()` on the nodes: `<b> <i> <s> <mark> <code> <a href>`; `<strong> <em> <del>` read as the same. Never `document.execCommand()` (differs per browser, own undo). `command()` is left only for typing-like inserts: text, line breaks, chips, cards.
- All words of the selection marked: the mark comes off (`liftOut()`), else it goes on; chips, cards and `[data-ws-skip]` stay. Same marks side by side are joined after any mark change or typed letters (`joinMarks()`), or `<i>y</i><i>def</i>` writes `_y__def_`. The selection stays in its direction (`selectionBackward()`, `setBaseAndExtent()`), so Shift+Arrow moves the same end after a mark.
- A caret with nothing selected: bold, italic, strike, highlight wait (`surface.wsMarks`) for the next letters (`beforeinput` insertText, `typeMarked()`), forgotten when the caret moves.
- A composed word (IME, dead keys, Android keyboards compose every word) never comes as insertText: `surface.wsComposing` keeps the marks waiting through it; on `compositionend` `composedMarked()` types the word again with `typeMarked()`, in the typing's undo step. Nodes are not changed mid-composition (it breaks the IME).
- Code puts the selection in one `<code>`, or the code around the caret back to text. Link opens the tip (Text field: workspace-editor-link-keys.md); `linkRange()` links a selection.

## How do undo and the pressed state follow?
- `change(apply)`: typing before it is a step, the change another; `announce()` sends `input` without a step (a note saves, the composer grows).
- `watch(fn)` / `notify()`: on history changes and `selectionchange`, once a frame; `formatState()` reads the selection, or the caret kept when left.

## Where are the labels?
- `tool_*` keys in `ws_editor_js_strings()` (`includes/workspace/screen.php`); tr.json keys reused where they existed; Strikethrough, Highlight, Code, Link with their keys and Text, Message, Note tools added. On a Mac the title shows ⌘, `aria-keyshortcuts` says Meta. The notes' `notes_bold` ... `notes_redo` keys are gone.

## What does it look like?
- Ghost icon buttons as before. The composer's groups are hidden below 768px and when its row is under 34rem (lists under 41rem; container query); the hint ends in an ellipsis. Pressed is the selection bar's accent tint; a disabled ghost button has no frame.

## How was it checked?
- Headless Chromium on `workspace.min.js`, stubbed `api.php`: composer, notes (phone size too), approval box: marks, pressed, focus, undo/redo, shortcuts, waiting marks, IME words (CDP), backward selections, links, cells, Enter sends, autosave, widths 760-1400px. Not Safari, Firefox, a phone or a running site.
