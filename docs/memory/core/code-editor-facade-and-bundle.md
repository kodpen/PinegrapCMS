---
name: code-editor-facade-and-bundle
description: The code editor facade window.PgCodeEditor (assets/js/pg_code_editor.js): lazy CodeMirror 6 bundle, opening an editor, locks and islands, tracked ranges, registries, bundle build
---

## What is the code editor and where does it live?
- Engine: CodeMirror 6, one bundle `assets/lib/code_editor/cm6.bundle.js` (~600 KB, `window.PgCM`). Nothing calls PgCM directly; screens call the facade `assets/js/pg_code_editor.js` (`window.PgCodeEditor`), served raw.
- The bundle is fetched the first time an editor opens (`PgCodeEditor.load()`), so a page that never opens one never downloads it. The page says where it is: `PgCodeEditor.configure({ bundle: '…/cm6.bundle.js?v=<stamp>' })` (`includes/designer_screen.php`); without it the URL is derived from the facade's own script URL.
- The designer page loads no CodeMirror 5 (`'code_editor' => false`): its code view, file tabs and code dialog (`codemirror_modal.js`, `window.openCodeEditor()`, API unchanged) run on the facade. The other panel screens keep CM5 (`get_codemirror_includes()`) until they migrate.

## How does a screen open an editor?
- `PgCodeEditor.open(host, opts).then(function (ed) {})` = load + create; `PgCodeEditor.attach(textarea, opts)` takes a textarea's place and writes every edit back into it (CM5's fromTextArea).
- `opts`: `language` (html|css|javascript|json), `value`, `readOnly` (focusable, refuses changes), `lineWrapping`, `theme` ('dark'|'light'), `keymap` ('vscode'|'default'), `phrases` (engine texts), `labels.syntaxError`, `onChange`, `onSelection`, `onFocus`, `onBlur`, `onLockHit`, `keys`.
- Instance: `getValue/setValue(text, keepHistory)`, `replaceRange(from, to, text, force)` (false when a lock refused it), `applyExternal` (past the locks, outside the undo history), `syntaxTree()`, `sliceDoc`, `setLanguage`, `setReadOnly`, `goTo`, `scrollTo`, `selection()`, `openSearch`, `findNext/Previous`, `replaceNext/All`, `destroy`.
- No `_sdT()` / `lang()` in the facade: every text is passed in by the caller.

## How do locks and tracked ranges work?
- `ed.lock([{ from, to, tag, allow: [[from, to], ...] }])`: a lock is read-only; its `allow` islands stay editable (an attribute's value, an element's text). A single change touching a lock outside an island is refused (`onLockHit` once per 1.2 s burst); a multi-change transaction (replace all) keeps the changes outside the locks. `setValue()` bypasses the locks; `ed.lockAt(pos)` / `ed.canEdit(from, to)` ask without changing.
- Edge rule: a lock does not absorb text typed at its edges (startSide 1 / endSide -1); an island does (-1 / 1), so appending to an element's text grows the island and keeps the lock around it intact.
- `ed.track([{ id, from, to, data, grow, cls }])`: ranges mapped through every edit; `ed.tracked(id)`, `ed.allTracked()`, `ed.trackedAt(pos)` (innermost first). `grow` as for islands. `cls` draws the range; `ed.mark(id, from, to)` highlights one. The designer's node map is built on these.

## How are features added?
- `PgCodeEditor.addCompletionSource('html', source)`: joins every editor of that language; the source gets `(context, { tree, node })`, the syntax around the cursor, so it never touches PgCM (the designer's `_sdCodeClassCompletion()` is the pattern). `PgCodeEditor.addExtension(function (opts, cm, inst) { return ext; })`: any engine extension (Emmet, minimap, a linter) for every editor created after it.
- The VS Code keymap replaces the default keymaps, so `autocompletion({ defaultKeymap: false })` when it is on.

## How is the bundle built and checked?
- `tools/code_editor/`: `package.json` (exact versions), `package-lock.json`, `entry.js` (the exports). `php tools/build_code_editor.php` runs `npm ci` + esbuild in a temp folder and writes the bundle with a banner listing the packages; `--check` rebuilds and compares (CI step). Upgrade: change package.json, `npm install` there, rebuild, commit all three.
- When the facade changes, run it in jsdom (locks, islands, tracked ranges, search, attach); there is no repo test for it.
