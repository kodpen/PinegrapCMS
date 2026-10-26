---
name: designer-content-roles-panels
description: The Attributes and Styles panels at content level: read-only outside a marked area, User Styles always, _sdPanelLockReason / _sdPanelLock, the refused commit points
---

## Are the Attributes and Styles panels editable at content level?
- Only for nodes `_sdCanEditNode(node)` allows: inside a marked area and not in a closed shared component. The design's User Styles never, at any place: the save does not write `style_custom_*` at this level.
- Before F5 every row was editable: a class, attribute or inline style changed on the canvas and the save (`pg_designer_merge_restricted_tree()`) dropped it.

## How is it decided and drawn?
- `_sdPanelLockReason(nodes, part)` answers `''` (editable), `'area'`, `'shared'`, or `'design'` for `part === 'design'`; `''` for a designer and in view mode (which has its own nudge). A multiple selection edits only when every node may.
- `_sdPanelLock(container, why)` disables the drawn inputs and buttons (their handlers never fire), hides the remove/add marks (`.sd-panel-readonly` in `style_designer.css`) and puts the reason on top. `_sdContentReason(why)` is the text, shared with the toast of `_sdContentNudge()`. Class chips still point at their control.
- Locked parts: the Attributes panel (single and multiple selection), the Styles panel's element.style card, its User Styles card and the Create button.

## Which commit points refuse as well?
- `addClassToken`, `_addMultiClass` (`_sdMayEditAll`), `_writeInlineStyleValue` (`_sdMayEdit`), `_writeUserStylesText` (the design nudge), so a path that bypasses the drawn panel is refused with the toast.

## How is it tested?
- `tests/designer_panels_readonly_test.php` runs `_sdPanelLockReason()` and `_sdContentReason()` with `_sdCanEditNode()` under Node. The disabling itself is DOM work, not unit tested.

Related: docs/memory/designer/designer-content-roles-ui-guards.md
