---
name: code-editor-facade-and-bundle
description: The code editor facade window.PgCodeEditor (assets/js/pg_code_editor.js): lazy CodeMirror 6 bundle, opening an editor, locks and islands, tracked ranges, registries, format, bundle build
---

## What is the code editor and where does it live?
- Engine: CodeMirror 6, one bundle `assets/lib/code_editor/cm6.bundle.js` (~700 KB, `window.PgCM`). Nothing calls PgCM directly; screens call the facade `assets/js/pg_code_editor.js` (`window.PgCodeEditor`), served raw.
- The bundle is fetched the first time an editor opens (`PgCodeEditor.load()`), so a page without one never downloads it. The page says where it is: `PgCodeEditor.configure({ bundle: '…/cm6.bundle.js?v=<stamp>' })` (`pg_code_editor_includes()`, `designer_screen.php`).
- The product's only code editor: designer code view and dialog (`codemirror_modal.js`), panel fields (docs/memory/ui/panel-code-fields.md), legacy page designer, CKEditor source mode.

## How does a screen open an editor?
- `PgCodeEditor.open(host, opts).then(function (ed) {})` = load + create; `PgCodeEditor.attach(textarea, opts)` takes a textarea's place and writes every edit back into it.
- `opts`: `language` (html|css|javascript|json), `value`, `readOnly` (focusable, refuses changes), `lineWrapping`, `theme`, `keymap` ('vscode'|'default'), `minimap` ([[code-editor-minimap]]), `phrases` (engine texts), `labels.syntaxError`, `onChange`, `onSelection`, `onFocus`, `onBlur`, `onLockHit`, `keys`.
- Instance: `getValue/setValue(text, keepHistory)`, `replaceRange(from, to, text, force)` (false: a lock refused), `applyExternal` (past locks, no undo step), `syntaxTree()`, `sliceDoc`, `setLanguage`, `setReadOnly`, `setTheme`, `goTo`, `scrollTo`, `selection()`, `openSearch`, `findNext/Previous`, `replaceNext/All`, `format()` ([[code-editor-format]]), `destroy`.
- No `_sdT()`/`lang()` in the facade: the caller passes every text. `findAll()`: hits without an editor ([[code-view-tab-search]]).

## How do locks and tracked ranges work?
- `ed.lock([{ from, to, tag, allow: [[from, to], ...] }])`: a lock is read-only; its `allow` islands stay editable (an attribute's value, an element's text). A single change touching a lock outside an island is refused (`onLockHit` once per 1.2 s burst); a multi-change transaction (replace all) keeps the changes outside the locks. `setValue()` bypasses the locks; `ed.lockAt(pos)` / `ed.canEdit(from, to)` ask without changing. `lockGutter: true` draws a lock marker beside each locked line (open when the line has an island), titled by `lockTitle(tag, partly)`.
- Edge rule: a lock does not absorb text typed at its edges (startSide 1 / endSide -1); an island does (-1 / 1), so appending to an element's text grows the island and keeps the lock around it intact.
- `ed.track([{ id, from, to, data, grow, cls }])`: ranges mapped through every edit; `ed.tracked(id)`, `ed.allTracked()`, `ed.trackedAt(pos)` (innermost first). `grow` as for islands, `cls` draws it. `ed.mark(id, from, to)` highlights one; the others keep `grow`/`cls` (PR #1108).

## How are features added?
- `PgCodeEditor.addCompletionSource('html', source)`: joins every editor of that language; the source gets `(context, { tree, node })`, the syntax around the cursor, never PgCM (`code-editor-completion.md`). `PgCodeEditor.addExtension(function (opts, cm, inst) { return ext; })`: any engine extension (Emmet, minimap, a linter) for every later editor.
- The VS Code keymap replaces the default keymaps, so `autocompletion({ defaultKeymap: false })` when it is on.

## How is the bundle built and checked?
- Never by hand: from `tools/code_editor/`: `package.json` (exact versions), `package-lock.json`, `entry.js` (the exports). `php tools/build_code_editor.php` runs `npm ci` + esbuild in a temp folder and writes the bundle with a banner; `--check` rebuilds and compares (CI). Upgrade: package.json, `npm install` there, rebuild, commit all three.
- When the facade changes, try locks, islands, search and attach in jsdom; tracked ranges have a Node test (`tests/code_editor_test.php`).
