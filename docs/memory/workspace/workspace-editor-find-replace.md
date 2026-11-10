---
name: workspace-editor-find-replace
description: Find and replace in the Workspace writing boxes (Ctrl+F, openFind()): where the shortcut works, how matches are found and painted, how a replacement keeps marks and undo, its labels and tests
---

## Where does Ctrl+F find in the box?
- `shortcut()` in `assets/js/workspace_editor.js` opens `openFind()` for Ctrl/Cmd+F (no Shift) only on a surface with an Editor (`surface.wsEditor`): the message box, the expanded box, notes, `richField()`. Table cells, one-line fields and the rest of the page keep the browser's own find; a second Ctrl+F inside the tip also reaches the browser.
- The shortcut sheet (Ctrl+/) lists Ctrl+F for an Editor (`shortcutRows()`).

## What does the tip hold?
- `ws-link-tip ws-find-tip` in the `tip` slot, `role="dialog"`, over the box: Find field (prefilled with a one-line selection, else the box's last query), count, previous, next, close; Replace with field, Replace, Replace all; Match case. Fields carry `aria-label`, the checkbox a `<label>`; the count is `role="status"` `aria-live="polite"` ("3 of 12", "No matches").
- Enter in Find goes to the next match, Shift+Enter the one before, Enter in Replace with replaces the current one. Escape or close: the focus goes back to the box with the current match selected. A click outside or a window resize closes it as the link tip.
- Query, replacement and Match case stay on the Editor (`findQuery`, `findReplacement`, `findCase`) for the next Ctrl+F.

## How are matches found and shown?
- `findSegments()` lists the box's text nodes; a `<br>`, a block's edge, a chip, a card or a `[data-ws-skip]` result is a `\n` piece with no node, so a one-line query never crosses or changes it. `findMatches(text, query, matchCase)` gives non-overlapping [start, end]; without Match case `findFold()` compares letters one for one, Turkish I, İ, ı and i all as i.
- Painted with the CSS Custom Highlight API (`::highlight(ws-find)`, `::highlight(ws-find-current)` in `backend.src.css`); where a browser lacks it nothing is painted, the count and Escape's selection still work. The text and its selection are not touched while the tip is open. The box's own `input` refreshes the count.

## How is a replacement made?
- `findReplace(segments, matches, replacement)` writes the new words into the piece a match starts in and cuts the rest of the match from the pieces after it, so marks around each piece stay; an emptied text node goes, and so does a mark (`b i s mark code a`) it leaves empty. Made inside `editor.change()`: Replace and Replace all are one undo step each. Only the box's content changes; no new syntax, render, `ws_plain_text()` and API are as before.

## Where are the labels?
- `find_*` keys in `ws_editor_js_strings()` (`includes/workspace/screen.php`): Find in the text, Replace with this, Match case, Go to the previous/next match, Close find, Replace, Replace all, `{var:1} of {var:2}`, No matches. "Next match" was not reused: its Turkish is a sports match.

## How was it checked?
- `tests/workspace_editor_find_test.php` runs `findFold()`, `findMatches()`, `findReplace()` under Node and holds the wiring. Headless Chromium on `workspace_editor.js` alone: a match across a bold mark, Replace, Replace all, Escape back to the box, each step undone by Ctrl+Z. Not Safari, Firefox, a phone or a running site.

Related: docs/memory/workspace/workspace-editor-link-keys.md, docs/memory/workspace/workspace-composer-later.md
