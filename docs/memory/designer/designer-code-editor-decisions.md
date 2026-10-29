---
name: designer-code-editor-decisions
description: The designer's code editor: owner rules (2026-10-19) and lasting decisions behind it (CodeMirror 6 facade, lazy load, half-locked code, one emitter), and which PRs built which part
---

## What is the code editor?
- The bottom panel's HTML column has an Edit switch. Off (default): the clickable tree (`renderHtmlTree()`), nothing extra loaded. On: a code editor over the page's real markup, two-way with the node tree; the design's CSS/JS files, a dynamic region's PHP and a classic page's layout open as tabs in the same editor.
- It is built on the facade (`PgCodeEditor`) the whole panel uses; CodeMirror 5 left the product (`ui/panel-code-fields.md`).

## Which rules did the owner set (2026-10-19)?
- The designer page must not get heavier: the editor bundle loads on the first Edit, never on open.
- Legacy designs keep working: no tree-shape change, no new save path.
- Head and foot rows are shown as the page prints them, read-only. Half-locked code: only attribute values and text change (`<body>` attributes, `_locked` nodes, derived classes, shared components, system widgets). The rest is free.
- Changes apply while typing.
- New features (Emmet, format, lint, minimap, multi-file search) plug into the facade without rework.

## Which decisions hold?
- Engine CodeMirror 6 (owner). Monaco was rejected for its weight: 2.5 MB core plus 0.7-7 MB of workers.
- One emitter (`_sdEmitNode()` with a range map), one set of frame rows shared with the HTML tree, the server converter for new elements, no JS twin of `pg_di_node()`.
- HTML is never formatted: the page view is printed by the emitter already.

## Which PRs built it, and where is each part described?
- Facade #503, view #504, incremental refresh #505, editing #521, file tabs and CM5 removal #522, class completion #523, panel screens #525/#527: `designer-code-view-model.md`, `designer-code-view-editing.md`, `core/code-editor-facade-and-bundle.md`.
- Bindings as `{{token}}` #669: `designer-code-view-bindings.md`.
- Emmet on Mod+E #2909: `core/code-editor-emmet.md`.
- Format (Shift+Alt+F) #1909, Format code buttons #2072 and in the bottom view's strip (#2993): `core/code-editor-format.md`.
- Minimap #1955, its toggle #2125: `core/code-editor-minimap.md`.
- Search across the open tabs #1871: `code-view-tab-search.md`.
