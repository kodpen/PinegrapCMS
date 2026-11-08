---
name: designer-code-view-part
description: The code view cut out of the designer's core as the part 'code' (style_designer_code.js): what moved, what stays at open, the stubs, the engine still to move (D5)
---

## What is in the code part?
- `assets/js/style_designer_code.js` (#8166): the code view's runtime: `_sdCodeToggle()`, the document and range map (`_sdCodeDocument()`), the selection both ways, the file tabs and the search in all tabs. Handed to every editor; it runs on the switch, a file tab, a layout or dynamic region opened as code, or at open when the view was left on. The core keeps what open needs (`_sdCodeInit()`, the tab strip, both completion sources, `_sdCodeBoundProps()`, `_sdCodePhrases()`), the typed-changes engine (TYPED CHANGES, BINDINGS, with `_sdCodeLockOf()`, `_sdCodeOpenTag()`, `_SD_CODE_APPLY_MS` that it calls) and stubs: before the part ran a redraw, Ctrl+S or a flush has nothing to reach; after, `_sdCodePart()` calls it synchronously.

## What is left to move?
- the code view's typed-changes engine (about 930 lines: TYPED CHANGES and BINDINGS, the binding completion as a stub, `_sdCodeLockOf()`, `_sdCodeOpenTag()`, `_SD_CODE_APPLY_MS`) into `style_designer_code.js`, dropping the kit entries the part then defines itself (`_sdCodeOnChange`, `_sdCodeLockTitle`, `_sdCodeOnLockHit`, `_sdCodeTextKind`, `_sdCodeLockOf`, `_sdCodeOpenTag`); the tests that cut engine functions from the core then read the part. Then theme panel, Google fonts browser, interactions.

Related: docs/memory/designer/designer-parts-on-demand.md
