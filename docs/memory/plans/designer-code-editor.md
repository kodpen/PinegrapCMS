---
name: designer-code-editor
description: Plan (2026-10-19) for the editable code view in the visual designer (HTML of the page, CSS/JS files, find and replace): rules, slices E1-E7, open questions, where to start
---

## What is being built?
- The bottom panel's HTML column gets an Edit switch. Off (default): today's clickable tree (`renderHtmlTree()`), nothing extra loaded. On: a code editor over the page's real markup, two-way with the node tree; the design's CSS/JS files open as tabs in the same editor, with find and replace.
- It becomes the designer's default code editor and later replaces CodeMirror 5 across the panel (`get_codemirror_javascript()` screens), so it is built as a facade other screens can call.

## Which rules did the owner set (2026-10-19)?
- The designer page must not get heavier: the editor loads on the first Edit. Today the designer loads CodeMirror 5 on every open (40 files, ~2.3 MB, three modals use it); once those move to the facade, CM5 leaves the page.
- Legacy designs keep working: no tree-shape change, no new save path.
- Head and foot rows are shown as the page prints them, read-only. Half-locked code: only attribute values and text change (`<body>` attributes, `_locked` nodes, derived classes). The rest is free.
- New features (Emmet, format, lint, minimap, multi-file search) must plug in without rework.

## Which decisions are made?
- Owner (2026-10-19): engine CodeMirror 6 (one bundle, syntax tree with positions, change filters for the locks; Monaco rejected for weight: 2.5 MB core + 0.7-7 MB workers); shared components and system widgets are half-locked in code mode (attribute values and text, `_sharedDirty` marked); changes apply while typing.
- One emitter: `generateHTML()` is split into `_sdEmitTree()` that returns the text and a range map (node -> open tag, attributes, text, close tag). Tree view, code view and the saved output all read it. Design: docs/memory/plans/designer-code-editor-design.md
- Head/foot rows come from one `_sdPageFrameRows()` shared by the tree and the code view (today `renderHtmlTree()`, the canvas and `_sdPreviewDocHtml()` each assemble their own copy; the preview's order is the closest to `get_page_content.php`).
- Existing nodes change in place by range; a new element typed in the editor goes through the server converter (`designer/import_fragment`), never a JS twin of `pg_di_node()`.
- Apply while typing is debounced and runs only when the edited region parses without an error node; one `saveState()` per edit burst.

## Slices (one PR each)
- E1 (done): facade `assets/js/pg_code_editor.js`, lazy loader, bundle, `tools/build_code_editor.php`; see docs/memory/core/code-editor-facade-and-bundle.md.
- E2 read-only code view: Edit switch, click -> select, selection -> scroll, text replaced on tree change.
- E3 emitter range map, lock levels as protected ranges.
- E4 in-place edits: attributes (Attrs-panel write rules), text per node kind, tag name; undo.
- E5 structural edits: insert, delete, move, reorder, via the converter.
- E6 CSS/JS tabs replace `#sd-as-modal`; `window.openCodeEditor()` adapter for the three modal uses; find/replace; CM5 removed from `designer_screen.php`.
- E7 extras: class completion (`PG_CLASS_SUGGESTIONS`), binding tokens, Emmet, format, lint, minimap. Changelog `[NEW]` line at E6.

## Open questions (owner)
1. Engine: CodeMirror 6 (one 700 KB file, syntax tree with positions, change filters for the locks; recommended) or Monaco (VS Code itself: 2.5 MB core + 0.7-7 MB workers, locks by plugin, ESM loader)?
2. Shared components and system widgets in code mode: locked, half-locked or free (inline edit marks `_sharedDirty`)?
3. Apply while typing, or an Apply button?
4. `buildNodeHTML()` has no callers; remove with E3?

## Where to start?
E1. Build the bundle with esbuild into `assets/lib/code_editor/` (pinned versions, a build tool under `tools/` like `build_js.php`); the facade exposes create/setValue/getValue/setLocks/find/goto/destroy and takes labels from the caller so `_sdT()` stays in `style_designer.js`.
