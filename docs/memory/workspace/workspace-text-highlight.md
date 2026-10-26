---
name: workspace-text-highlight
description: ==x== as a highlight (<mark>): the pair rule, old shapes that stay text, server and editor twins, plain text, guest page, notes import, sums, table cells
---

## Which text becomes a highlight?
- `==words==` on one line, the signs touching the words and no third `=` beside either pair: `(?<!=)==(?=\S)([^=\n]{1,300}?)(?<=\S)==(?!=)`, the notes import's rule. The same regex sits in every place below; change them together.
- Stays text, as stored before highlights were drawn: `a == b`, `x === y`, `== Title ==`, `=====`, `===x===`, a pair across two lines. `tests/workspace_render_test.php` keeps those equal to `ws_render_text()`.
- No word boundary is asked: `a==b and c==d` draws `a<mark>b and c</mark>d` (pinned in the test). A comparison written in backticks is code and stays as written.

## Where is it drawn?
- `ws_format_text()` (includes/workspace/render.php), in `$emphasis` after bold and strike: messages, notes, list items, a link's words. Code, links and bare addresses are set aside first, so `https://x/?q==a==` stays one address.
- `ws_guest_render()` (guests.php) draws the same `<mark>`.
- The writing box: `inlineNodes()` (assets/js/workspace_editor.js) reads the pair into a `<mark>`, `serializeNode()` writes it back as `==x==` (words holding a `=` are written without the signs: the server could not read them back). The twin has no lookbehind: a regex literal with one is a syntax error in Safari before 16.4 and stops the whole workspace.min.js; the sign before the pair is a group, as italic's is. `tests/workspace_editor_highlight_test.php` runs it under Node against `ws_format_text()` and the round trip.
- The tool `highlight` (workspace-editor-toolbar.md), Ctrl/Cmd+Shift+H (no browser key; Notion's), labels `tool_highlight`, `fmt_highlight`: on the selection bar and the notes row (`ws-nb-tools`); the composer's row keeps B I S (its width steps are set for them).
- No CSS: Bootstrap's reboot styles `<mark>` in both themes.

## What reads it as words?
- `ws_plain_text()` (API `text`, assistants, e-mail, banners) drops a paired `==` only; a lone `==` keeps its signs.
- `ws_calc_inline()` (calc.php) drops the paired signs before it looks for the last `=`, so `Kira ==15.000== + aidat 750 =` adds up as the plain line does.
- `ws_calc_number()` drops a pair around a whole figure (`==1.590==`), as it does `**`.

## Is a table cell starting with == a formula?
- No when it starts with a highlight pair: `ws_calc_is_formula()` (calc.php; used by `ws_calc_has_formula()` and `ws_calc_grid()`) and its twin `isFormula()` in the table window. `==A1` (no closing pair) stays a formula with its syntax error, `=A1+B1` as before; pinned in `tests/workspace_calc_test.php`.
- API `body`, MCP, search and the Markdown export stay raw.

## What does the notes import do?
- `ws_note_import_inline()` (notes_io.php) keeps `==x==` as written (it used to make it `**x**`).

## What is still open?
- Autoformat (typing `==x==` making the mark) waits on question 3 of plans/workspace-composer-tools.md.
