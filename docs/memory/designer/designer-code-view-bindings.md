---
name: designer-code-view-bindings
description: Data bindings in the designer's code view: a bound text, href, src or alt prints as {{token}}, typing a token binds, a literal unbinds, {{ offers the widget's tokens; where the rules live
---

## Why does the code view print {{token}} for a bound value?
- A system widget fills `props._bindings.text` / `href` / `src` / `alt` at render time (`_apply_bindings()`); the tree keeps the design-time value and the page's HTML (`generateHTML()`) prints that value for the server to replace. Shown as it is, the code view would offer a text whose change reaches nothing on the site. So with a sink (code view only) `_sdEmitNode()` prints from `_sdCodeBoundProps(n)`: a bound text as `_sdBindTextSlot(text, '{{token}}')` (the token takes the one run of words the markup wraps, as `pg_binding_text_slot()` does), a bound href/src/alt as `{{token}}`; a plain `<img>` gets it in its `_attrs` copy (`_sdCodeAttrsWith()`). Nothing bound: the props themselves, no copy.
- `_sdCodeIslands()` reads the printed text, so the token of a half-locked node (widget, shared component) is an editable island even when the node has no text of its own.

## What does typing do?
- `_sdCodeTextFromTyped(node, typed)`: inside a widget, on an element whose text the Bind Data panel can bind (`_sdCodeBindable()`; the typed text stands in for a plain element's missing text), the first `{{token}}` binds the text (`_sdNodeSetBinding()`), and the words it stood for (`_sdCodeSlotRun(props.text)`) go back in its place, so the design-time text survives a change to the markup around the token. Words typed beside the token are kept but never shown (the page prints the bound value alone unless the markup wraps one run); one toast says so. A text without a token typed over a bound one ends the binding and stands as written.
- `_sdCodeApplyBoundValue(node, prop, value)` from `_sdCodeSetAttr()`: an attribute (or an `<input>` button's `value`) that is exactly `{{token}}` binds the prop and stores nothing else; a literal over a binding ends it and is stored as before.
- Outside a widget, or on a prop the panel does not offer (a structural `<td>`, say), the braces are text like any other.
- `_sdNodeSetBinding(node, prop, value)` is the one writer of a binding: the panel's `_setBinding()` and the code view both call it (same-named `_attrs` entry dropped, stale `_bindFormats` dropped, `_sharedDirty` marked).

## What does {{ offer?
- `_sdCodeBindingCompletion()` (registered with `PgCodeEditor.addCompletionSource('html', …)` beside the class completion) answers only in the page's code view (`context.view === _sdCode.ed.view`), in a `Text` node or the value of `href` / `src` / `alt` / `value`, after `{{`: the tokens of the element's widget from `_swBindTokenOptions(node)`, label as detail, `}}` appended unless already there.
- `_swBindTokenOptions()` is the flat form of what the Bind Data panel draws: `_swBindTokenGroups(type)` (the fixed palettes, also behind `_swBindLabel()`), and for the two form views the form's fields (`_formFieldsCache`, fetched when missing), their `__label` tokens when the detail view shows labels, and the built-in field options.

Related: docs/memory/designer/designer-code-view-editing.md, docs/memory/designer/system-widget-bindings.md
