---
name: designer-layer-names
description: Rules for node customName (layer names) in designer blocks: one name per role, contextual generic names, no embedded numbers, no empty names.
---

## What do layer names mean?
`customName` is the name the operator reads in the tree; `Body`, `Item`, `Head` say nothing in a thirty-node tree. The name states the role.

## What are the naming rules?
- **The same role has the same name in every block.** `Kapat Düğmesi`, `Açılır Menü Öğesi`, `Sosyal Bağlantılar`, `Navbar Konteyneri`, `Menü Bağlantıları`, `Tablo Gövdesi`. Reuse existing names when writing a block; do not invent synonyms (`Anchor` / `Close` / `Toggle` / `Compare Table` were born that way).
- **Generic names take context:** a `<tbody>` is `Tablo Gövdesi`, a `.modal-body` is `Modal Gövdesi`, an `.offcanvas-body` is `Offcanvas Gövdesi`.
- **No number inside the key:** `_sdT('Tab {var}', 1)`, `_sdT('Item {var}', n)`. `'Tab 1'` splits the translation into three and breaks the `{var}` rule.
- **A wrapper and its child never share a name**; the name belongs to the wrapper.
- **Never write an empty `customName`.** If there is no name, omit the field; the tree then shows the label, not a blank row.
- Palette record `label` and visible `defaultProps` texts are wrapped in `_sdT()`; the checker stays silent for single capitalized words in that position, so verify by eye.

Related: docs/memory/designer/designer-sdt-translation.md
