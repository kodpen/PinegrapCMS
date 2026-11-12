---
name: js-function-name-twins
description: A test fails when a script source declares one function name twice in a scope (tests/js_function_names_test.php): why node --check misses it, what the scanner reads, the known twin
---

## What does the check catch?
- JavaScript lets a later `function name()` in the same scope replace the earlier one for every caller, and `node --check` parses the file. #8719 declared `compose` twice in `workspace_mail.js` (the answer drawer and New e-mail), so Reply by e-mail opened the wrong form; #8748 renamed it.
- `tests/js_function_names_test.php` (WM10, #8796) scans every `pinegrap/assets/js/*.js` and `pinegrap/*.js` that is not a `.min.js` bundle with `pg_test_js_duplicate_functions()` (`tests/helpers/js_function_names.php`), and the fixture `tests/fixtures/js_function_names/twin.js`. About 190 files take under a second.

## What does the scanner read?
- Not a parser. It steps over comments, strings, template literals with their `${…}`, and regular expressions (a slash after an operator, `(`, `,`, `{`, `;` or a word such as `return`), and keeps one name list per pair of braces.
- `function name(` counts only where a statement starts: after `{`, `}`, `;`, `async`, `export`, `default`, or a line break after a value. A named function expression (after `=`, `(`, `,`, `:`, `?`, `return`) names only itself.
- A block such as `if (…) { function a() {} }` is a scope of its own here, so a twin across a block and its function is not reported.

## Which twin is known?
- `style_designer.src.js` declares `findNodeById` twice with the same body (near lines 3769 and 8150); the test lists it by name, so any other twin fails. Removing the later copy rebuilds the designer bundle, which open changes of that file hold, so it waits for one of them.

## Tests?
- The fixture (a twin beside a named expression, a block, strings, a comment, a regex and a template), the scopes, the text it steps over, and every script of the tree.
