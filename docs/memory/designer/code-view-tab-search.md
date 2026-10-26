---
name: code-view-tab-search
description: Search in all tabs of the designer's code view (page and file tabs): the bar, PgCodeEditor.findAll(), why it searches only and does not replace
---

## What does it do?
- A magnifier at the right of the code view's tab strip, or Ctrl+Shift+F in any tab (`_sdCodeFindKey()` in each editor's `keys`), opens a bar under the strip (`_sdCodeFindShow()`, `#sd-code-find`). A one-line selection in the active tab fills the field.
- The hits of the page tab and every loaded file tab are listed by tab, unsaved text included (`_sdCodeFindRun()`), 500 per tab; match case, whole word and regexp toggles as in the per-tab search panel.
- A hit opens its tab and selects the match (`_sdCodeFindPick()`); Enter / Shift+Enter in the field walk the hits and keep the focus there, Escape closes the bar. A text changed since the search (typing, a canvas edit, a closed tab) is searched again instead of selecting a wrong range.

## Where is the matching?
- `PgCodeEditor.findAll(text, query, limit)` in the facade (`assets/js/pg_code_editor.js`), no engine needed: `query` is `{ search, caseSensitive, regexp, wholeWord }` or a string; a regexp reads `^`/`$` per line and empty matches are skipped. A hit is `{ from, to, text, line, col, lineFrom, lineText }`; `truncated` when the limit cut the list, `error` for an invalid pattern. Node tests in `tests/code_editor_test.php`.

## Why no replace across tabs?
- The page tab's text is locked in parts (frame rows, widgets, shared components) and a replace there goes through the tree; a cross-tab replace would mix that with plain file writes and unsaved tabs. Replace stays per tab (the engine's search panel), where the locks apply.
