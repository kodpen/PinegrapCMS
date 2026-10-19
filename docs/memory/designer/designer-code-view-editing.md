---
name: designer-code-view-editing
description: How a change typed in the designer's code view reaches the tree: dirty span, scope, owner rule, in-place rules per node kind, new elements as holders through the converter, undo, flush, file tabs
---

## How does typing reach the tree?
- `onChange` grows `_sdCode.dirty` (mapped through later changes) and arms a 400 ms timer; `_sdCodeApply()` runs then, or at once from `_sdCodeFlush()` (before `saveAjax()` and `_pgTabsSwitch()`). The facade's own writes are `busy`, not typing.
- Scope (`_sdCodeScopeOf`): the innermost tracked range holding the whole span: a node, the `<body>` tag (attributes go to the root node) or `content` (the page's content between the body tag and the scripts). The element is read from the syntax tree (`ed.syntaxTree()`, Lezer HTML); an error node or a mismatched/incomplete close tag in the scope means nothing is applied until the text parses (the lint marks it). When the element no longer covers the span, `_sdCodeApplyOuter()` widens to the parent.
- Owner rule (`_sdCodeOwnerOf`): an element whose `<` is where a node's range starts is that node (ranges are tight, `<` to the last character, grow false: a tag typed just before a node is new). An element with no owner but owned elements inside becomes a `semantic` wrapper (`_sdCodeNewSemantic`); one with none inside becomes a Custom HTML holder with the typed markup and goes to `designer/import_fragment` (`_sdCodeImport`); the answer replaces the holder, no answer leaves it. Text beside elements at body level or in a mixed plain element becomes `span` content nodes, as the converter does.

## What changes in place?
- `_sdCodeApplyTag`: `semantic` and heading (`h1`-`h6`) rename; other types keep the tag their type prints (toast).
- `_sdCodeApplyAttrs`: only attributes that differ from what the emitter printed (parsed from `_sdCode.text` at the entry's original range) are touched. `class` by token diff: tokens of `cssClass` go, others are the type's (toast), an icon's `bi-*` token sets `iconName`; `id` -> `props.id` + `_sdRetargetLabels()`; `style` -> `_sdApplyInlineStyle()` (the Attributes panel's splitter); `src/alt/width/height` of an image and `href/target/rel` of a link, a btn or `<a>` -> props; tooltip title/placement -> props; the rest -> `_attrs`. The root node's style is replaced as `_attrs.style`.
- `_sdCodeApplyText` by `_sdCodeTextKind()`: `plain` (semantic text node, entities decoded, pre/textarea/code untrimmed, `textPosition` for a button or link), `inner` (content element innerHTML minus its owned children, which stay nodes and go when removed), `outer` (Custom HTML = its markup), `none`.
- Children (`_sdCodeReconcileChildren`) are rebuilt only for a free node whose children the emitter placed (`entry.atomic` false) and whose text is not `inner`/`outer`; a half-locked node keeps its children. A shared component's or widget's elements met inside a page node keep that node's `shared_ref` in place (`_sdCodeSharedRefFor`); their own changes arrive with the node as scope and mark `_sharedDirty`.

## File tabs and the dialog
- A CSS/JS/JSON entry of the assets panel opens as a tab beside the page (`_sdCodeTabOpen(spec)`: `load`, `save`, `url` for an external link, `readOnly`). Ctrl+S in a file tab saves that file (`_sdCodeSaveShortcut()`), elsewhere the design. The props panel's code fields use `window.openCodeEditor()` (`codemirror_modal.js`, on the facade); the designer loads no CodeMirror 5.

## Undo, selection, locks
- `saveState('run:code')` once per apply, then `render()`; the refresh writes back the tree's spelling of the text (quotes, entities, indentation) with a cursor-preserving diff. Ctrl+Z in the editor undoes typed text and applies again; Ctrl+Z outside restores the tree and the text follows.
- `selectedNode` is dropped when its node left every tree (`_sdCodeNodeExists`). A refused keystroke shows one toast per 2.5 s naming the lock (`_sdCodeOnLockHit`).
- Read-only follows `_collab.mayEdit` (`_sdCodeSyncMode()`); the switch reads Edit when typing is allowed, Code otherwise.
