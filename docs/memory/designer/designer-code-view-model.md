---
name: designer-code-view-model
description: The designer's code view: range map from one emitter, the document and its lock levels, head/foot rows shared with the HTML tree, what lives in the facade and what in the designer
---

## How does the editor know which text belongs to which node?
- `_sdEmitNode(n, indent, base, sink)` writes what `generateHTML()` writes and, with a sink, records every node as `{node, from, to, sid}` in document order (`sink.full` also writes widget trees whole, loop areas as `<!-- loop -->` comment pairs). A component's children are located inside its `toHTML()` output by the child string; not found -> their records are dropped and the component counts as one block. Attribute and text positions are read from the text afterwards (`_sdCodeOpenTag()`, `_sdCodeIslands()`), so a component needs no map of its own.
- `_sdCodeDocument()` builds the document: frame rows, the `<body>` tag from `_sdPageBodyAttrs()`, the body, foot rows; one entry per row and node (`key` = `sid:_id`, ids repeat across widgets, so nodes are matched by identity) and the facade locks (`lock([{from, to, tag, allow}])`). The entries go to the editor as tracked ranges, so they follow every edit; the text is replaced only when the emitted text differs from what the editor got last (`_sdCodeRefresh()`). The `content` entry grows at both ends; on an empty page it is an empty range, which dies at the next edit once it stops growing (the selection mark did that until PR #1108: nothing typed reached the canvas).
- `renderHtmlTree()` is the single hook: in code mode it calls `_sdCodeRefresh()` and returns (62 callers stay untouched). Keys typed inside `.cm-editor` are the editor's: `initKeyboard()` steps aside, Ctrl+S still saves.

## What are the lock levels?
| Level | What | Rule |
|---|---|---|
| full | html/head rows, body open/close, foot scripts, region and loop markers, system widget output, nodes `_sdCanEditNode()` refuses | no change at all |
| half | `<body>` attributes (root node), `_locked` nodes and their descendants, derived classes (`getNodeBuiltinClasses()`) and builtin attributes without `propKey`, `SYS_ATTR_RE` attributes | only attribute values and text inside the element |
| free | everything else | any edit |
- `_sdCodeLockOf(node)` answers `{level, attrs, text}` from the same questions the canvas asks (`_sdCanEditNode()`, `_locked`/`hasLockedAncestor()`, `_resolveSharedSid()`, `_collab.mayEdit`); half = a facade lock with islands over the attribute values and the node's own text. A loop area locks only its two marker lines; a `shared_ref` and a widget's root wrapper lock nothing (what is inside decides). The facade's change filter drops a change outside the islands and reports it (`onLockHit`); the server gate `pg_designer_merge_restricted_tree()` stays the authority.
- The locked lines carry the HTML tree's lock icon in the gutter (`lockGutter: true`): closed when nothing on the line may change, open when the line has an island; the tooltip and the refused-keystroke toast share `_sdCodeLockTitle(tag, partly)`. Free lines carry none.

## Where do head and foot rows come from?
- `_sdPageFrameRows()` (rows `{tag, attrs, void, inner, comment}`): charset, title, meta from `pageSettings.meta`, without Bootstrap rows the framework + theme + icons, Google Fonts, linked styles (theme after the Bootstrap row), classic theme file, `pg-page-css`, `pg-user-styles`; foot scripts linked, JSON, classic, modules. The tree draws them with `_sdFrameRowHtml()`, the code view prints them with `_sdFrameRowText()` (`_sdAttrEsc()` escapes quotes); the preview keeps its own copy because it needs absolute URLs.

## What stays in style_designer.js, what goes to the facade?
- Facade (`pg_code_editor.js`): engine, theme, keymap, search, completion sources registry, lock filter, range marks, tabs. No `_sdT()`: labels are passed in by the caller.
- Designer: emitter, range map, apply rules, lock levels, hooks. Translation keys stay in `style_designer.js` so `pg_designer_i18n_keys()` keeps one scan.
