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
- A panel code field (`pg_code_editor_attach()`): every field gets the `.pg-code-field-tools` row with a small "Minimap" toggle (`pg-code-field-minimap`); off at the start. The row goes with `destroy()`.
- But a read-only field of one line (a cron command under Site Settings › Updates and maintenance) gets no row: a minimap of one line shows nothing. The help under it keeps .5rem from the field (`.pg-code-field-ro ~ .form-text`).
- The row keeps its buttons at their own height when the hint beside them wraps (`align-items: flex-start` on `.pg-code-field-tools`): on a phone the three-line hint stretched them into tall boxes (Appearance › Panel CSS, SEO's robots fields).
- The designer's bottom code view (`style_designer.js`) has no toggle; the minimap is always on there.

## How is it drawn?
- `_minimapExtension(cm, on)` gives the facet `showMinimap` with `displayText: 'blocks'` (cheaper than characters on a long page) and `showOverlay: 'mouse-over'` (the viewport box only while the pointer is over it). The host element has the class `pg-ce-minimap`. It draws on a canvas, so jsdom cannot show it; a Chromium run did.
- The package draws its whole canvas inside every editor update and re-reads the colour and font of every highlight class first (a hidden element and a forced style recalculation each): about 45 recalculations and 88 ms a key on an empty designer page, 7 and 30 ms without the minimap (issue #11944).
- `_minimapTame()` wraps the plugin's `create` once (found in `showMinimap.extensions`, the plugin is not exported): the reads are kept until the editor's classes or `data-bs-theme` change, and a draw waits `MINIMAP_QUIET_MS` (300 ms) after the last text or size change (a side panel drag too); a scroll or selection draws on the next frame. After: 9 recalculations and 29 ms a key. An unknown shape leaves it as shipped.
- Test: `tests/code_editor_test.php` reads the facet off a state under Node on the bundle, and checks the option, `setMinimap`/`hasMinimap`, the designer's call, both toggles and the deferred draw.

Related: docs/memory/core/code-editor-facade-and-bundle.md, docs/memory/designer/designer-code-editor-decisions.md
