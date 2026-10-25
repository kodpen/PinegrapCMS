---
name: no-form-in-form
description: No form inside a form in the designer: semantic forms at any depth, widgets the server wraps in a form (_SD_FORM_WIDGET_TYPES), blocks, widgets and components that bring one.
---

## Why is a form never put inside a form?
- The HTML parser drops a nested `<form>` tag, so the inner block's fields and button submit with the outer form: a Newsletter block in a Contact Form, or in a Login widget, posted to that form's processor. In a custom form widget `_cfAdoptOnDrop()` also makes them its fields.
- The old Rule 7 compared the target's own tag with the dragged element's top tag; a block or a `cf_field` payload has none, so blocks nested freely. The double-click walk-up (PR #1582) made it reachable from a selected field.

## How does `canDrop()` decide?
- `_sdDragCarriesForm(data) && _sdInForm(target)` refuses, asked before the page root's free pass, so a widget tree's root is covered too. Drag, the Overview tree, double-click and the command palette all ask it; `_sdInsertSpot()` then walks past the form and lands right after it.

## What counts as being in a form (`_sdInForm()`)?
- The target is a semantic `form` or sits in one, at any depth (walk up with `_sdAnyParent()`).
- Or it is in the tree of a system widget whose renderer prints a `<form>` around that tree, which is not a node there (`_sdWidgetPrintsForm(sid)`, kinds in `_SD_FORM_WIDGET_TYPES`): `custom_form`; `login_form`, `forgot_password`, `registration`, `membership` (widgets.php, `_pg_member_form_wrap()`); `logout`, `change_password`, `set_password`, `account_profile`, `email_preferences`, `address_book` (widgets_account.php); `login_region` (its sign-in form); `express_order` (its host form).
- Some print it in one state only (signed in or out, before a reset is sent); each counts as always printing it.
- `account_security` is in the list but not "in a form": its blocks are semantic forms bound `_bindings.action = 'account_security_form'`, found as nodes. Only a tree with no bound block is wrapped whole, and dropping a form there to bind it is the way back to blocks (`account-widget-forms.md`).
- A new widget kind whose renderer wraps its tree in a `<form>` joins `_SD_FORM_WIDGET_TYPES`.

## What counts as bringing a form (`_sdDragCarriesForm()`)?
- The dragged or palette-built tree (`_sdDragTree()`) holds a semantic `form`, or `<form` in a node's own markup (`_sdNodeMarkup()`).
- A `shared_ref` in it to a widget of a kind in `_SD_FORM_WIDGET_TYPES` (`account_security` too), or to a component whose cached tree holds a form (each sid walked once). A tree not cached yet is not looked at.
- A new system widget (`source: 'palette'`, `type: 'system_widget'`) has no tree: its `extra.regionType` in `_SD_FORM_WIDGET_TYPES` counts. The System tab's "New" double-click passes that payload, as its drag does (it passed `{ type: 'shared_ref' }`, which named no kind).
- Kept on the payload (`data._sdForm`), since dragover asks on every mouse move.

## How is it tested?
- tests/designer_inline_drop_test.php (`..._no_form_in_form`, `..._no_form_in_member_widget`) runs the real `canDrop()`, `_paletteInsert()` and `_sdInsertSpot()` under Node, with `_isInsideSystemWidget()` and `_cfCfgOf()` stubbed by a node prop.

Related: docs/memory/designer/palette-component-rules.md, docs/memory/designer/palette-insert-at-selection.md
