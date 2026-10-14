---
name: designer-form-palette-and-fields
description: Designer form controls: label[for] protection, fieldset radio/checkbox groups, CF_PALETTE rules, overview-tree option folding.
---

## How is `label[for]` kept correct?
| What | Where |
|---|---|
| id changed -> label moves | `_sdRetargetLabels(oldId, newId)` — page tree AND every cached shared-component tree; owner marked dirty |
| `for` never written -> server matches | `pg_cf_link_labels()`, AFTER `pg_cf_apply_field_bindings()` (ids settle only there) |
- Server binds only in a wrapper with ONE label + ONE control. With more, which belongs to which is a guess, and a wrong `for` is worse than a missing one. `type="hidden"` is not labelled.

## What is a radio/checkbox group?
- A group is defined by `<fieldset>`: same-type boxes inside the same fieldset share one name, `<legend>` is the question's label (`_cfEnsureNames`, `_cfLeadOf`). Adopting each box separately split a three-answer question into three yes/no fields and a submission stored three values instead of one. A lone box outside a fieldset (KVKK consent) is its own field. The palette's "Onay grubu" / "Radyo grubu" blocks come as fieldset + legend.
- A field's type is the control itself; the panel has no type select. Changing the type = changing the control (Input panel `type`, or another palette block).

## What is in the palette "Form" group?
- `CF_PALETTE` / `CF_PALETTE_ORDER` (style_designer.js): text, email, phone, number, URL, password, textarea, select / multi-select, checkbox, checkbox group, radio group, switch, date, time, datetime, file, color, range, hidden, floating label, label, field group, submit, reset, CAPTCHA, form. Each is `div > label + control` (group: `fieldset > legend + boxes`), a Turkish default label, a seed `name`.
- A new control type goes into the palette; NOTHING is added to the `form_fields.type` enum: any `type` not in the list is a text box and `custom_form.php` validates it. (Exception: signature, see signature-receipt-and-output.)

## What does the overview tree hide?
- `_sdTreeFolded()`: when all children of `select` / `datalist` / `optgroup` are `<option>`, the row becomes a leaf with an "N seçenek" badge. Options are edited in the Options panel; five options = five rows pushing the fields on both sides of one question off screen.
- Second noise source: writing the same `customName` on a block's wrapper, its label AND its control. Only the wrapper carries the field's name.

Related: docs/memory/designer/signature-receipt-and-output.md
