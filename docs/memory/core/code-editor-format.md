---
name: code-editor-format
description: The code editor's format command: ed.format() and Shift+Alt+F for CSS, JavaScript and JSON through the bundled js-beautify, what refuses it, how it lands in the undo history (PR #NNNN)
---

## What does format do?
- `ed.format()` re-indents the whole text with the editor's `indentUnit` (spaces or a tab) through js-beautify (`PgCM.format.css` / `.js`; JSON goes through the JavaScript part). A closing new line stays as it was; at most two blank lines in a row are kept.
- Shift+Alt+F runs it in both keymaps (VS Code and default); the binding sits before the keymap, so a screen's own `keys` (high precedence) can still take the key.
- HTML is not formatted: the designer's page view is printed by its emitter (`_sdEmitNode()`) already. `format()` returns false there and the key falls through.

## When does it refuse, and how does it land?
- False, nothing changed: no formatter for the language, the editor is read-only, a lock (`ed.lock()`) overlaps the part that would change (`canEdit()` is asked first, so `onLockHit` is not called), or js-beautify threw.
- Only the part that differs (common prefix and suffix cut off) is replaced, in one transaction with `userEvent: 'input.format'` through the lock filter: one Ctrl+Z step, and `onChange` / `attach()` write-back hear it like typing. The cursor stays before the same non-blank character.
- True when the text changed or was already formatted.

## Where does the formatter come from?
- `js-beautify` pinned at an exact version in `tools/code_editor/package.json`; `entry.js` imports only `js/src/css/index.js` and `js/src/javascript/index.js` (not its HTML part, CLI or config loading). MIT, named in the bundle banner.
- Main bundle, not a separate chunk: the build tool writes one IIFE and the whole bundle is fetched on the first editor open already; it added ~78 KB (~19 KB gzip).
- Test: `tests/code_editor_test.php` runs `_formatText()` under Node on the bundle and checks the `format()` contract.

Related: docs/memory/core/code-editor-facade-and-bundle.md, docs/memory/plans/designer-code-editor.md
