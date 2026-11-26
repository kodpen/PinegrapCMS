---
name: code-view-empty-page-typing
description: Typing into the designer's code view on an empty page: the empty body line, the end of the <body> line as page content, and the Edit switch that never drops unapplied text (issue #11944)
---

## What did an empty page do before?
- Its document was `<body>` followed straight by the scripts: no line to type on. What was typed at the end of the `<body>` line stayed in the editor, reached no node, sent nothing and said nothing; Edit off and on again wiped it (issue #11944, reported on dev.pinegrap.com).
- Cause: `_sdCodeDocument()` wrote `bodyOpen + '\n'`; the `body` entry ended at the `>`, the `content` entry started after the line break. The end of the `<body>` line was in neither range, so `_sdCodeScopeOf()` found no scope and `_sdCodeApply()` dropped the span. A page with content had the same gap.

## What does the document hold now?
- An empty page's body is one indented empty line (`_SD_CODE_EMPTY_LINE`, four spaces) between the body tag and the scripts; the cursor can stand on it.
- The `content` entry starts right after the body tag's `>` (not after its line break) and still grows at both ends. Typed at the end of the `<body>` line, on the empty line or at column 0 of the scripts line: page content. Typed inside the body tag: the body's attributes, as before.

## What does the Edit switch do with text that is not on the page?
- `_sdCodeHoldsUnapplied()` runs before the view switches off: what waits for the 400 ms timer is applied first. If the editor's text still differs from what the tree prints (`_sdCode.text`), the view stays on and a warning toast names the reason: HTML not finished yet (the span is still dirty), or text outside the page content.
- A second switch within 6 s (`_SD_CODE_LEAVE_MS`) leaves the text out and switches off; typing between the two starts over (`_sdCodeOnChange()` clears `leaveAt`). A read-only session is never held.

## How was it checked?
- `tests/designer_code_view_empty_page_test.php` cuts `_sdCodeDocument()` and `_sdCodeScopeOf()` and moves the ranges the way the facade does for one insertion (red on the old code).
- Sandbox (`tools/setup_sandbox.sh`), headless Chromium, new Bootstrap 5 design: `<div class="container">` typed at the end of the `<body>` line reaches the canvas and survives Edit off/on; `<p>Hello` on the empty line too; `<section>` at the end of the `<body>` line of a page with content goes before it; `<p>Hi</` keeps the view on with the toast, a second switch closes it.
- The minimap's share of the typing cost: [[code-editor-minimap]].
