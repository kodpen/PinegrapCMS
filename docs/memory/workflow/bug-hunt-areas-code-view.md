---
name: bug-hunt-areas-code-view
description: The bug hunt of the visual designer's Code view (style_designer_code.js, 2026-11-26, #12006): the five faults fixed, what was read clean, what was not run
---

## Which area was hunted?
- The designer's Code view: `assets/js/style_designer_code.js`, and `pg_code_editor.js` where the view drives it. Asked for: typing lost when Edit is switched or the selection changes, an empty page, invalid HTML, attributes with quotes, undo, the cost per key on a large page. Issue #11944 (empty page, the end of the `<body>` line, the minimap) had landed: [[code-view-empty-page-typing]].

## What did the hunt's PR fix?
- Typed text waiting for the 400 ms pause, or HTML not finished yet, was replaced by the tree's text on any redraw: a click on another line of the editor (it selects that element), a click on the canvas, a panel. `_sdCodeRefresh()` now keeps the editor's text and ranges while `_sdCode.dirty` is set and the tree prints what it printed before; a changed tree still writes its text.
- An attribute still being typed was applied: `data-x=` became `data-x=""`, a quote typed inside a value (`title="say "hi""`) cut the value and added attributes, and the refresh rewrote the line under the cursor. The parser's error nodes inside the open tag were not checked; `_sdCodeHasError()` holds the element (and the body tag) until it parses.
- Words typed straight into a container, row or column vanished at the redraw: only body level and a mixed plain element made text runs into `span` nodes. `_SD_CODE_SPAN_PARENTS` adds the three grid types.
- After a switch had held the view on, the next switch within 6 s left newly typed text out without a word; typing now clears `leaveAt`.
- A free btn component's typed label was dropped at the redraw and a content role's btn was fully locked: the btn had no text kind. A link or `<button>` btn is `inner` now (its label is printed as it is); a badge (escaped label) and an `<input>` btn (label in `value`) stay `none`.

## What was read clean?
- The empty page's line and scope after #11944; the body tag's islands; `_sdCodeOpenTag()` on single, double and unquoted values; undo in the editor (the refresh's rewrites are outside the history and the typed change maps over them); file tabs and the search in all tabs.
- Cost per key: `_sdCodeOnChange()` maps two positions and resets a timer; the selection listener ignores typing. The full emit, diff and range rebuild run per pause, click or redraw, not per key.

## What is still open?
- A tree change (a panel edit, an import answer) while typed text waits still writes the tree's text over it; applying the waiting text inside a redraw would nest `render()`.

## What was not run?
- No browser: the parts were run under Node with the editor bundle's HTML parser (`tests/designer_code_view_typing_test.php`); the canvas, the panels and the real editor were not driven.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/designer/designer-code-view-editing.md, docs/memory/designer/designer-content-roles-text-nodes.md
