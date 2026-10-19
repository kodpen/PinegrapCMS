---
name: designer-code-editor
description: Plan (2026-10-19) for the visual designer's code editor: owner rules, decisions, what was built (PRs), what is left (Emmet license, formatter, panel screens off CodeMirror 5), open questions
---

## What is being built?
- The bottom panel's HTML column gets an Edit switch. Off (default): today's clickable tree (`renderHtmlTree()`), nothing extra loaded. On: a code editor over the page's real markup, two-way with the node tree; the design's CSS/JS files open as tabs in the same editor, with find and replace.
- It becomes the designer's default code editor and later replaces CodeMirror 5 across the panel, so it is built as a facade other screens can call.

## Which rules did the owner set (2026-10-19)?
- The designer page must not get heavier: the editor loads on the first Edit. Today the designer loads CodeMirror 5 on every open (40 files, ~2.3 MB, three modals use it); once those move to the facade, CM5 leaves the page.
- Legacy designs keep working: no tree-shape change, no new save path.
- Head and foot rows are shown as the page prints them, read-only. Half-locked code: only attribute values and text change (`<body>` attributes, `_locked` nodes, derived classes). The rest is free.
- New features (Emmet, format, lint, minimap, multi-file search) must plug in without rework.

## Which decisions are made?
- Owner (2026-10-19): engine CodeMirror 6 (Monaco rejected for weight: 2.5 MB core + 0.7-7 MB workers); shared components and system widgets are half-locked in code mode; changes apply while typing.
- One emitter (`_sdEmitNode()` with a range map), one set of frame rows, the server converter for new elements, no JS twin of `pg_di_node()`.
- Built (PRs #503 facade, #504 view, #505 incremental refresh, #521 editing, #522 file tabs and CM5 removal, E7 class completion): docs/memory/designer/designer-code-view-model.md and designer-code-view-editing.md; facade docs/memory/core/code-editor-facade-and-bundle.md.

## Open questions (owner)
1. `buildNodeHTML()` has no callers; remove?
2. `esc()` escapes text, not quotes: a `"` in an attribute value breaks the tag in `generateHTML()` (the frame rows use `_sdAttrEsc()`). Fix in the emitter, with the PHP twin?

## What is left, and where to start?
- Completion of data-binding tokens (`{{…}}`) in text and attribute values; the class source (`_sdCodeClassCompletion`) is the pattern.
- Emmet: `@emmetio/codemirror6-plugin` carries no license (repo and package, 2026-10-19), so it is not bundled; the `emmet` core package is MIT and an expand-abbreviation command of our own on it is the way, if wanted.
- A formatter for CSS/JS tabs (the page view is normalised by the emitter already), a minimap, search across the open tabs.
- The panel screens move to `pg_code_editor_attach()` one by one (`edit_file.php` done; the list and the recipe: docs/memory/ui/panel-code-fields.md), then CodeMirror 5 leaves the product.
