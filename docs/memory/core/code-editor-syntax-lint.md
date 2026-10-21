---
name: code-editor-syntax-lint
description: The code editor's syntax lint (_syntaxLinter): what is marked, empty documents, the whole document parsed in slices first (files over 100 KB), what stops a running parse, the 200-mark cap
---

## What does the syntax lint mark?
- `_syntaxLinter()` (`assets/js/pg_code_editor.js`) marks every error node of the engine's syntax tree as "Syntax error" (the caller's `labels.syntaxError`); JSON asks the JSON parser instead. At most 200 marks; the cap is checked before each node. `lint: false` (plain text) or `setLint(false)` turns it off. It runs 500 ms after the last edit.

## Why is an empty document not checked?
- The HTML parser leaves a zero-length error node at the start of an empty document and the JSON parser reports an unexpected end, so every empty field was red until #629. An empty document, and a blank JSON one, return no marks.

## Why does the lint parse the whole document first?
- The engine parses in the background no further than 100,000 characters past the visible part (`MaxParseAhead`), and a tree cut there carries an error node at the cut. The lint read that tree: in a file over ~100 KB a valid line at the cut was marked (bootstrap.css 147 KB line 4958 `.label-danger[href]:focus {`, theme_modern.css line 5244), real errors after it were never shown, and an early run marked the cut of the first, shorter parse instead. Fixed in #1059 (issue #783).
- Now the lint asks `ensureSyntaxTree(state, docLength, 50)` and reads the tree only when it covers the whole document. Each call parses for 50 ms and keeps its progress; the next slice follows after `setTimeout(…, 0)`, so a 2 MB file is parsed without freezing the page (measured: 2.4 MB CSS, error on the last line marked after ~4 s, longest main-thread block ~110 ms on a loaded test box). An edit reuses the tree outside the changed part, so the next lint is quick.

## What stops a lint run that is still parsing?
- A text change: the loop stops at the next slice and resolves with nothing (the lint extension also ignores results for an older document).
- A replaced linter or a closed editor: `pgCeCreate()` keeps `lintGen`, bumped by every linter it builds (`lintExtension()`: first build, `setLanguage()`, `setLint()`) and by `destroy()`. `_syntaxLinter(cm, lang, labels, alive)` checks `alive()` before each slice and returns without resolving when it is false.
- Why: the lint extension applies any result whose document is unchanged, and re-appends its state field when the linter was reconfigured to nothing. `create_file.php` calls `setLanguage()` then `setLint()` on a type change, so without the check css to txt during a long parse would leave CSS error marks on a plain-text field that nothing clears, css to js would let the old result overwrite the new one, and a closed editor would parse on until the end. Part of #1059.

## How was it checked?
- `tests/code_editor_test.php` (`test_code_editor_lint_skips_an_empty_document`, `test_code_editor_lint_reads_the_whole_document`, `test_code_editor_lint_stops_when_the_linter_is_replaced`) read the facade's source.
- Headless Chromium with the facade and the bundle: bootstrap.css (147 KB) and theme_modern.css (145 KB) get exactly the error lines of a full parse; before the fix one false mark at the cut and nothing past it.
