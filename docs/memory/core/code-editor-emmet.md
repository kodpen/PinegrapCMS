---
name: code-editor-emmet
description: The code editor's Emmet: the MIT emmet core in the lazy bundle, ed.expandAbbreviation() on Mod+E (why not Tab), the context from the syntax tree, tab-stops, locks, the `emmet` option (PR #2909)
---

## Where does Emmet come from?
- The `emmet` core 2.4.11 (MIT) with `@emmetio/abbreviation` 2.3.3, `@emmetio/css-abbreviation` 2.1.8 and `@emmetio/scanner` 1.0.4 (all MIT), pinned in `tools/code_editor/package.json` / lock file; `entry.js` exports `PgCM.emmet = { expand, extract }`. Named in the bundle banner. It added ~78 KB minified to the main bundle, fetched only on the first editor open.
- `@emmetio/codemirror6-plugin` is never bundled: it carries no licence (repo and package). The command is the facade's own.

## How is it run?
- `ed.expandAbbreviation()` (true when it expanded) on **Mod+E** (Ctrl+E, Cmd+E on a Mac), in both keymaps, before the keymap (a screen's own `keys` can still take it).
- Not Tab: Tab indents and accepts a completion, and in HTML text every word is a valid abbreviation (`hello` becomes `<hello></hello>`), so a Tab trigger would eat ordinary typing. Mod+E has no binding in the VS Code or default keymaps (the emacs-style Ctrl-E is the Mac's Ctrl, not Cmd).
- When it returns false (nothing to expand) the key falls through to the browser.

## Which context does it use?
- `_emmetContext(cm, state, pos)` reads the syntax tree (lookup cache reset first, as for completion): markup when the node at the cursor is HTML `Text`, `Element` or `Document` (never in a tag, comment, `<script>`), with the enclosing element's tag name as Emmet's context (`.item` in `<ul>` gives `<li>`); stylesheet inside a CSS `Block` or a `style=""` value (`Styles`), in a CSS editor or inside an HTML one. JavaScript and JSON editors never expand.
- `_emmetSnippet()` extracts the abbreviation on the cursor's line (look-ahead for an auto-closed `]`/`}`/`)` in markup only: in `style=""` it stopped at the quote) and expands it with a tab indent; CM6 `snippet()` turns each tab into the editor's indent unit after the line's own indent.
- Emmet's fields become `${n:placeholder}` tab-stops through private markers; `${`/`#{` in the expanded text is escaped, so only Emmet's fields are fields. Tab / Shift+Tab walk them, Escape leaves them. One undo step.

## What refuses it?
- `emmet` off, a language other than html/css, a read-only editor, a non-empty or multiple selection, no abbreviation, Emmet throwing, `canEdit()` false over the abbreviation.
- Markup also inside a lock's island (`_insideLock`): an island of a half-locked element takes text, not new elements (owner rule 2026-10-19). CSS in a `style=""` island is only text, so it expands.

## How is it switched?
- On by default (the key does nothing unasked). Per editor `opts.emmet: false`; page default `PgCodeEditor.configure({ emmet: false })`. While open `ed.setEmmet(on)` (rebuilds the keymap compartment), `ed.hasEmmet()`. No screen switches it today; no button.

## How is it tested?
- `tests/code_editor_emmet_test.php`: the pins and licences in the lock file, the entry and banner, the context and snippet blocks under Node on the bundle (a fake editor for `snippet()`), the facade wiring. Mod+E, read-only, locks, islands, Tab between fields and undo were run once in jsdom on a real view.

Related: docs/memory/core/code-editor-facade-and-bundle.md, docs/memory/plans/designer-code-editor.md
