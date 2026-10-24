---
name: designer-content-roles-text-nodes
description: Which designer nodes a manager or user may edit as text outside a marked area (_sdIsTextNode()): content, semantic, the btn and badge components; why not an old card.
---

## Which nodes count as text at content level?
- `_sdIsTextNode(node)` (`style_designer.js`) answers `_sdCanEditNode(node, 'text')` for a node with a string `props.text`: a content heading, paragraph, button, link, span or text; any semantic node; the atomic components `btn` and `badge`.
- The same answer drives the double-click edit (the virtual button label too), the read-only panel's Text row and the fade on the canvas (`sd-content-locked`) and in the Overview (`sd-tree-nocontent`). A badge has no double-click target for any role: its label changes only through the Text row.
- A system widget and a shared component not opened to the operator refuse everything before this rule (`_sdInClosedShared()`); inside an opened component the rule applies as on the page.

## Why are the btn and badge components text?
- They keep their label in `props.text`, and the save (`pg_designer_merge_restricted_tree()`) copies `props.text` of every node that has one, keeping the address and the look. The call-to-action button of every ready-made block is a `component`/`btn` (`palette-component-atomic.md`).
- Symptom fixed: a manager or user who double-clicked a block's button label, on the page or in an opened shared component, got "You can edit text, and anything inside an area marked as editable."; the panel had no Text row and the button was faded, while a semantic `<a class="btn">` beside it was editable.

## What does the code view offer a content role?
- A text island only where `_sdCodeApplyText()` writes it back: `_sdCodeLockOf()` asks `_sdCodeTextKind(node) !== 'none'` too, so a content or semantic node. A btn or badge component prints its own label, so there it stays fully locked and a keystroke gets the lock toast; before, the island took it and the next redraw dropped it unsaved.
- A designer's code view still takes a typed btn label and drops it at the redraw (no component text kind); the panel and the double-click save it.

## Why not every component with a text prop?
- An older opaque component (card, pricing, hero, features, still in trees saved before palette components were exploded) is edited in place through props the save does not take (`title`, `subtitle`, `btnText`). Offering it would show a change on the canvas that the save drops.

## How was it checked in the browser?
- A design page with page text, a login_form widget, a closed shared header and two opened components (one granted to the user). The manager saved every page and opened-component text and button label; the user saved those of the page and of the granted component, and the ungranted one was refused. Each saved label was on the site after a reload. Widget and closed-header texts were refused with "System widgets and shared components are managed by designers." and stayed as stored; the System and Shared palette tabs were hidden and `shared_component` `create` answered "Permission denied.". The administrator saved all of them.
- In the code view, as manager and as user, a keystroke at the end of a btn label (on the page and in the granted component) got the lock toast and changed nothing; the headings beside them applied and were saved.

## Where is it tested?
- `tests/designer_content_text_test.php`: the editor's answer and the code view's lock under Node, and the save taking a button's and a badge's label while keeping the button's address and look.
