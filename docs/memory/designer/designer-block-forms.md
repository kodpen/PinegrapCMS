---
name: designer-block-forms
description: How a ready-made designer block writes a form that can be sent and read aloud: a label for every field, a real submit button, no unknown component types, and what the audit and the block test check.
---

## Why could the older block forms not be sent?
- A Button component (`componentType: 'btn'`) is an `<a href="#">` unless its `btnElement` says otherwise. The login, contact, newsletter, footer newsletter and 404 search blocks used it as their only button, so Enter and the button did nothing; the editor's audit also reported "The form has no submit button" on every one of them.
- `navbar-search` and `footer-newsletter` asked for `componentType: 'form-search'` / `'form-email'`, which have no `COMPONENTS` entry. The emitter draws an unknown component type as nothing, so both forms were saved with a button and no field.

## How does a block write a form now?
- Every field has a `<label for>`: a visible `form-label`, or `visually-hidden` when the design shows none (newsletter, search, notify forms). The field is a semantic `input` (its `id` in `_attrs` is promoted to `props.id` by `createNode()`); a search field sits in a `form` with `role="search"`.
- The submit is either the Button component with `btnElement: 'button', btnType: 'submit'` (keeps the Button panel: variant, size; used by the older blocks) or a semantic `<button type="submit">` (the maintenance notify forms). No `href` on a submit button.
- The forms are samples: they have no `action` until the operator wires them (Bind Data, or a custom form).

## What does the audit accept as a submit?
- `form_no_submit` (`_validationRules` in `style_designer.js`) counts a content button, a semantic `button`, and a Button component drawn as `<button>`/`<input>` with `btnType: 'submit'`. A Button component left as a link does not count.
- `input_no_label` looks the field's `props.id` up among the `for` values of every `label` in the tree.

## What keeps this from coming back?
- `tests/designer_blocks_test.php`: every `createNode('component', …)` type in `createFromPalette()` has a `COMPONENTS` entry; every Button component inside a block `form` has `btnElement` `button` or `input`, and every block form has a submit.
- The same test reads every semantic `input`/`select`/`textarea` in `createFromPalette()`: its id expression (`_attrs` `id` or the `id` prop) must be the `for` of a `label` written in the same builder or field helper (`_cfField`, `_lmFloating`, `_mnNotify`); outside a form an `aria-label` also counts. Every label `for` must name such a field. Ids are compared as source expressions (`nlId + '_email'`), so a field and its label use the same expression.
- The theme/a11y harness also checks in the rendered HTML that every input has a label and every control a name (`docs/memory/designer/designer-block-theme-surfaces.md`).
