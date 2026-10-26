---
name: designer-content-roles-panels
description: The Attributes panel and the Styles pane at content level: read-only outside a marked area and in closed shared components, User Styles always read-only, Create and Export hidden, why
---

## When are the Attributes panel and the inline style read-only?
- At content level (manager, user) when `_sdPanelLockReason(nodes)` answers: `'area'` for a node outside a marked area, `'shared'` for one in a shared component not opened to the operator, `''` when every node is theirs. A multi-selection is all or none, as `_sdMayEditAll()`. A designer is never locked by it.
- Applied by `_sdPanelLock(box, why)` at the end of `renderAttrsPanel()`, `renderAttrsMulti()` and, for the `element.style` card, `renderStylesTabPane()`: every field and button disabled (values stay visible), the class chips lose their remove mark, a line on top gives `_sdContentReasonText(why)` (the same text as the refusal toast `_sdContentNudge()`).
- The inner selection of a text (`_sdTextInnerAttrs`) is not locked: it formats the node's own text.

## Why read-only and not just refused on save?
- Outside a marked area the save takes only `props.text` and the image address (`pg_designer_merge_restricted_tree()`). A class, id, attribute or style typed there showed on the canvas and was dropped by the save without a word.

## What about User Styles?
- They are the design's (`style_custom_css`), which the content level never writes. Their card is always read-only there (`'design'`: "User Styles belong to the whole design and are managed by designers."), and `_sdApplyContentChrome()` hides the Styles toolbar's Create (`sd-styles-create`) and Export to a File (`sd-styles-extract`).

## Where is it tested?
- `tests/designer_content_panels_test.php` runs the reason helpers under Node with the permission questions stubbed.

Related: docs/memory/designer/designer-content-roles-ui-guards.md, docs/memory/designer/designer-content-roles-save-gate.md
