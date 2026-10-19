---
name: designer-code-editor-design
description: Design decided for the designer code view: range map from one emitter, lock levels and protected ranges, text/tree sync loop and its guard, attribute write rules, hook points in style_designer.js
---

## How does the editor know which text belongs to which node?
- `_sdEmitTree(tree, opts)` writes what `generateHTML()` writes today and, when asked, records per node: open-tag range, each attribute's name and value range, the text range, the close-tag range, plus a lock level. A component's children are located inside `comp.toHTML()` output by the child string (unique by construction); not found -> the component is half-locked as a whole.
- The ranges are handed to the editor as mapped marks, so they follow every edit; the text is never re-emitted while the operator types. Re-emission happens only when the tree changed from outside the editor (canvas, panels, undo) and replaces the whole document.
- `renderHtmlTree()` is the single hook: in code mode it calls the code view's refresh and returns (62 callers stay untouched).

## What are the lock levels?
| Level | What | Rule |
|---|---|---|
| full | html/head rows, body open/close, foot scripts, region and loop markers, system widget output, nodes `_sdCanEditNode()` refuses | no change at all |
| half | `<body>` attributes (root node), `_locked` nodes and their descendants, derived classes (`getNodeBuiltinClasses()`) and builtin attributes without `propKey`, `SYS_ATTR_RE` attributes | only attribute values and text inside the element |
| free | everything else | any edit |
- Enforced with the editor's change filter: a change touching a full range or the tag/structure part of a half range is dropped and a toast says which lock; the panel's own gates (`_sdMayEdit`, content roles, collab view mode) are asked the same way, and the server gate `pg_designer_merge_restricted_tree()` stays the authority.

## How is an edit applied?
- Debounce 400 ms; parse the region of the outermost changed node from the editor's syntax tree; stop on an error node (the lint shows it).
- Existing element (its open tag starts inside a tracked open range): tag name -> `props.tag` (semantic, heading) or a conversion to `semantic` for container/row/col/component when a derived class was removed (toast); `class` -> `cssClass` minus derived tokens; `id` -> `props.id` + `_sdRetargetLabels()`; `style` -> `_writeInlineStyleValue()`; attributes with `propKey` -> the prop; the rest -> `_attrs` through the Attrs panel's rules; text: `semantic` plain (entities decoded), `content` heading/paragraph/span innerHTML.
- Element without a tracked owner: its outer HTML goes to `designer/import_fragment`; the reply's nodes are spliced at the parsed position, `_cfAdoptOnDrop()` for controls. Removed tracked node -> removed (locks permitting). Order of children follows the parsed order.
- `saveState('run:code:' + nodeId)` before the first applied change of a burst; `render()` after; `_sdCodeApplying` guards the loop so the editor keeps its text and cursor.
- Edits inside a shared component mark `_sharedDirty[sid]` (same as the canvas).

## Where do head and foot rows come from?
- `_sdPageFrameRows()`: charset, title, viewport, meta from `pageSettings.meta`, framework + icons links, theme (`_sdThemeHeadHtml()`), Google Fonts, linked/inline/user styles in `_sdAssetsInPlay()` order, foot scripts in the front end's order (linked, JSON, classic, modules). Both the tree and the code view print these rows; the preview keeps its own because it needs absolute URLs.

## What stays in style_designer.js, what goes to the facade?
- Facade (`pg_code_editor.js`): engine, theme, keymap, search, completion sources registry, lock filter, range marks, tabs. No `_sdT()`: labels are passed in by the caller.
- Designer: emitter, range map, apply rules, lock levels, hooks. Translation keys stay in `style_designer.js` so `pg_designer_i18n_keys()` keeps one scan.
