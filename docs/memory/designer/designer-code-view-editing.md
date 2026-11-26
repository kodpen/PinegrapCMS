---
name: designer-code-view-editing
description: How a change typed in the designer's code view reaches the tree: dirty span, scope, owner rule, in-place rules per node kind, new elements as holders through the converter, undo, flush, file tabs
---

## How does typing reach the tree?
- `onChange` grows `_sdCode.dirty` (mapped through later changes) and arms a 400 ms timer; `_sdCodeApply()` runs then, or at once from `_sdCodeFlush()` (before `saveAjax()` and `_pgTabsSwitch()`). The facade's own writes are `busy`, not typing.
- Scope (`_sdCodeScopeOf`): the innermost tracked range holding the whole span: a node, the `<body>` tag (attributes to the root node) or `content` (body tag to scripts). The element comes from the syntax tree (Lezer HTML); an error node (also a half-typed attribute: `name=`, a quote inside a value) or a mismatched/incomplete close tag means nothing is applied until it parses. An element no longer covering the span: `_sdCodeApplyOuter()` widens to the parent.
- Owner rule (`_sdCodeOwnerOf`): an element whose `<` is where a node's range starts is that node (tight ranges, grow false: a tag typed just before a node is new). No owner but owned elements inside: a `semantic` wrapper (`_sdCodeNewSemantic`); none inside: a Custom HTML holder sent to `designer/import_fragment` (`_sdCodeImport`), replaced by the answer.
- Text beside elements at body level, in a container, row or column (`_SD_CODE_SPAN_PARENTS`) or in a mixed plain element becomes `span` content nodes, as the converter does.

## What changes in place?
- `_sdCodeApplyTag`: `semantic` and heading (`h1`-`h6`) rename; other types keep the tag their type prints (toast).
- `_sdCodeApplyAttrs`: only attributes that differ from what the emitter printed (`_sdCode.text` at the entry's original range). `class` by token diff: `cssClass` tokens go, others are the type's (toast), an icon's `bi-*` sets `iconName`.
- Attributes to props: `id` -> `props.id` + `_sdRetargetLabels()`; `style`: [[designer-code-view-style]]; image `src/alt/width/height`, link/btn/`<a>` `href/target/rel` -> props; title and tooltip -> `_sdCodeSetTitleAttr()` unless `_attrs` has the name; the rest -> `_attrs`; the root's style -> `_attrs.style`.
- `_sdCodeApplyText` by `_sdCodeTextKind()`: `plain` (semantic text node, entities decoded, pre/textarea/code untrimmed, `textPosition` of a button or link), `inner` (content innerHTML or a btn's label, minus its owned children, which stay nodes and go when removed), `outer` (Custom HTML = its markup), `none`.
- Children (`_sdCodeReconcileChildren`) are rebuilt only for a free node whose children the emitter placed (`entry.atomic` false) and whose text is not `inner`/`outer`; a half-locked node keeps its children. A shared component's or widget's elements inside a page node keep its `shared_ref` in place (`_sdCodeSharedRefFor`); their own changes arrive with the node as scope and mark `_sharedDirty`.

## What do file tabs and the dialog do?
- A CSS/JS/JSON entry of the assets panel opens as a tab beside the page (`_sdCodeTabOpen(spec)`: `load`, `save`, `url` of an external link, `readOnly`). Ctrl+S in a file tab saves that file (`_sdCodeSaveShortcut()`), elsewhere the design. Search in all tabs: [[code-view-tab-search]]. Props panel code fields: `window.openCodeEditor()` (`codemirror_modal.js`).

## How do undo, selection and locks work?
- A redraw while typed text waits and the tree is unchanged keeps the text ([[bug-hunt-areas-code-view]]).
- `saveState('run:code')` once per apply, then `render()`; the refresh writes back the tree's spelling (quotes, entities, indentation) with a cursor-preserving diff. Ctrl+Z in the editor undoes typed text and applies again; outside it restores the tree and the text follows.
- `selectedNode` is dropped when its node left every tree (`_sdCodeNodeExists`). A refused keystroke shows one toast per 2.5 s naming the lock (`_sdCodeOnLockHit`).
- Read-only follows `_collab.mayEdit` (`_sdCodeSyncMode()`); the switch reads Edit when typing is allowed, else Code.
