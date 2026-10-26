---
name: code-editor-minimap
description: The code editor's minimap: @replit/codemirror-minimap in the lazy bundle, the opt-in `minimap` option and page default, setMinimap(), on in the designer, what it cost
---

## What is it and where does it come from?
- A scaled outline of the text beside the scroll bar: `@replit/codemirror-minimap` pinned at an exact version (0.5.2) in `tools/code_editor/package.json`, exported from `entry.js` as `PgCM.minimap`. MIT (its package.json; the npm package ships no LICENSE file), named in the bundle banner. Its peer ranges (`@codemirror/state|view|language|lint`, `@lezer/common|highlight`) accept the pinned versions; its one dependency, `crelt`, is already in the bundle.
- Main bundle, so it arrives with the first `PgCodeEditor.load()` and never with a page that opens no editor. It added ~15.8 KB (~5.3 KB gzip).

## How is it turned on?
- Opt-in. Per editor: `opts.minimap: true|false`; page default: `PgCodeEditor.configure({ minimap: true })`. The editor's own option wins over the page default.
- While open: `ed.setMinimap(on)` (a compartment; a toggle button can call it).
- The designer turns it on for every editor of the page in its `configure()` call (`includes/designer_screen.php`); panel screens leave it off.

## How is it drawn?
- `_minimapExtension(cm, on)` gives the facet `showMinimap` with `displayText: 'blocks'` (cheaper than characters on a long page) and `showOverlay: 'mouse-over'` (the viewport box only while the pointer is over it). The host element has the class `pg-ce-minimap`. It draws on a canvas, so jsdom cannot show it; a Chromium run did.
- Test: `tests/code_editor_test.php` reads the facet off a state under Node on the bundle, and checks the option, `setMinimap` and the designer's call.

Related: docs/memory/core/code-editor-facade-and-bundle.md, docs/memory/plans/designer-code-editor.md
