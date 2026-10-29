---
name: code-editor-minimap
description: The code editor's minimap: @replit/codemirror-minimap in the lazy bundle, the opt-in `minimap` option and page default, setMinimap()/hasMinimap(), the toggle buttons (PR #2125), on in the designer
---

## What is it and where does it come from?
- A scaled outline of the text beside the scroll bar: `@replit/codemirror-minimap` pinned at an exact version (0.5.2) in `tools/code_editor/package.json`, exported from `entry.js` as `PgCM.minimap`. MIT (its package.json; the npm package ships no LICENSE file), named in the bundle banner. Its peer ranges (`@codemirror/state|view|language|lint`, `@lezer/common|highlight`) accept the pinned versions; its one dependency, `crelt`, is already in the bundle.
- Main bundle, so it arrives with the first `PgCodeEditor.load()` and never with a page that opens no editor. It added ~15.8 KB (~5.3 KB gzip).

## How is it turned on?
- Opt-in. Per editor: `opts.minimap: true|false`; page default: `PgCodeEditor.configure({ minimap: true })`. The editor's own option wins over the page default.
- While open: `ed.setMinimap(on)` (a compartment); `ed.hasMinimap()` says whether it is on (the editor's option, else the page default).
- The designer turns it on for every editor of the page in its `configure()` call (`includes/designer_screen.php`); panel screens leave it off.

## Where can a person switch it?
- The code editor dialog (`codemirror_modal.js`): "Minimap" beside "Format code" at the left of the footer, a toggle button (`aria-pressed`, `active` while on), read-only dialogs too. It starts from `hasMinimap()` (on in the designer); the last choice is kept in a page variable and passed as `minimap` to the next open, since each open builds a fresh editor. Not stored in the browser. Label: `PgCodeModalLabels['Minimap']` (`designer_screen.php`).
- A panel code field (`pg_code_editor_attach()`): every field, read-only too, gets the `.pg-code-field-tools` row with a small "Minimap" toggle (`pg-code-field-minimap`); off at the start. The row goes with `destroy()`. No new CSS: the row's existing flex style plus Bootstrap's `gap-2`.
- The designer's bottom code view (`style_designer.js`) has no toggle; the minimap is always on there.

## How is it drawn?
- `_minimapExtension(cm, on)` gives the facet `showMinimap` with `displayText: 'blocks'` (cheaper than characters on a long page) and `showOverlay: 'mouse-over'` (the viewport box only while the pointer is over it). The host element has the class `pg-ce-minimap`. It draws on a canvas, so jsdom cannot show it; a Chromium run did.
- Test: `tests/code_editor_test.php` reads the facet off a state under Node on the bundle, and checks the option, `setMinimap`/`hasMinimap`, the designer's call and both toggles.

Related: docs/memory/core/code-editor-facade-and-bundle.md, docs/memory/designer/designer-code-editor-decisions.md
