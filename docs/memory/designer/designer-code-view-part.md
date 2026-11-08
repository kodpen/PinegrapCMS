---
name: designer-code-view-part
description: The code view cut out of the designer's core as the part 'code' (style_designer_code.js): the runtime and the typed-changes engine, what stays at open, the stubs
---

## What is in the code part?
- `assets/js/style_designer_code.js`: the code view's runtime (#8166): `_sdCodeToggle()`, the document and range map (`_sdCodeDocument()`), the selection both ways, the file tabs and the search in all tabs; and its typed-changes engine (#8213): TYPED CHANGES (`_sdCodeOnChange()`, `_sdCodeApply()`, the reconcile, the attribute and text writes, `_SD_CODE_APPLY_MS`), the {{token}} BINDINGS (`_sdCodeTextFromTyped()`, `_sdCodeApplyBoundValue()`, the binding completion), `_sdCodeLockOf()` and `_sdCodeOpenTag()`.
- It runs on the switch, a file tab, a layout or dynamic region opened as code, or at open when the view was left on. Nothing is typed before it ran, so the engine needs nothing at open.

## What stays in the core?
- What open needs: `_sdCodeInit()`, the tab strip, both completion sources, `_sdCodePhrases()`, and the bound props of the output (`_sdCodeBoundProps()`, `_sdCodeAttrsWith()`, `_sdCodeBindToken()`: `_sdEmitNode()` with a sink prints a bound value as {{token}}).
- Stubs: before the part ran a redraw, Ctrl+S, `_sdCodeFlush()` (a save, a tab switch) or `_sdCodeBindingCompletion()` has nothing to reach; after, `_sdCodePart()` calls it synchronously (entry points `refresh`, `saveShortcut`, `flush`, `bindingCompletion`).

## Which tests read the part?
- The tests that cut engine functions (`designer_code_view_attrs`, `_binding`, `designer_content_text`, `designer_fx` panel, `bs6_site_output` members) read the core and the part one after the other. `bs6_site_output` skips a part's `var x = K.x;` aliases, so a moved `/* @pg-site-output */` member keeps its mark.

Related: docs/memory/designer/designer-parts-on-demand.md
