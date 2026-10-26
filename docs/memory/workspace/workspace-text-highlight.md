---
name: workspace-text-highlight
description: ==x== drawn as a highlight (<mark>) by the server (step 4a of the composer tools): the pair rule, old shapes that stay text, plain text, guest page, notes import, sums, the table-cell trap
---

## Which text becomes a highlight?
- `==words==` on one line, the signs touching the words and no third `=` beside either pair: `(?<!=)==(?=\S)([^=\n]{1,300}?)(?<=\S)==(?!=)`, the notes import's rule. The same regex sits in every place below; change them together.
- Stays text, as stored before highlights were drawn: `a == b`, `x === y`, `== Title ==`, `=====`, `===x===`, a pair across two lines. `tests/workspace_render_test.php` keeps those equal to `ws_render_text()`.
- No word boundary is asked: `a==b and c==d` draws `a<mark>b and c</mark>d` (pinned in the test). A comparison written in backticks is code and stays as written.

## Where is it drawn?
- `ws_format_text()` (includes/workspace/render.php), in `$emphasis` after bold and strike: messages, notes, list items, a link's words. Code, links and bare addresses are set aside first, so `https://x/?q==a==` stays one address.
- `ws_guest_render()` (guests.php) draws the same `<mark>`.
- No CSS: Bootstrap's reboot styles `<mark>` in both themes.

## What reads it as words?
- `ws_plain_text()` (API `text`, assistants, e-mail, banners) drops a paired `==` only; a lone `==` keeps its signs.
- `ws_calc_inline()` (calc.php) drops the paired signs before it looks for the last `=`, so `Kira ==15.000== + aidat 750 =` adds up as the plain line does.
- API `body`, MCP, search and the Markdown export stay raw.

## What does the notes import do?
- `ws_note_import_inline()` (notes_io.php) keeps `==x==` as written (it used to make it `**x**`).

## What is still open?
- A table cell whose text starts with `==` (a highlighted cell) is read as a formula: `ws_calc_has_formula()` (calc.php) and its JS twin in workspace_editor.js test only the first `=`. Not fixed yet; the JS side goes with the editor half.
- The editor half (twin in `inlineNodes()` / `serializeNode()`, a toolbar button) and autoformat wait (plans/workspace-composer-tools.md).
