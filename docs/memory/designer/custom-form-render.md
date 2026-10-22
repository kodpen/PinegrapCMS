---
name: custom-form-render
description: custom_form visitor-facing behaviour: hide-from-visitor (office_use_only) and pg_cf_apply_field_bindings render rules (name/id, select options, required, defaults).
---

## How does "Ziyaretçiden gizle" (hide from visitor) work?
- Legacy meaning: `custom_form.php` reads the field from POST only if the form has the `office_use_only=true` hidden field **and** the submitter opened the page with edit authority; otherwise it writes the default value.
- Render (`_render_system_widget_custom_form($…, $mode)`): with `$mode === 'edit'` + `check_edit_access()` the control is kept and the hidden field printed; for a visitor the control, its `for` label and the emptied wrapper are dropped.

## What does `pg_cf_apply_field_bindings` do at render?
- A control maps to its row by `name` (`pg_cf_field_map` has lowercase keys); `name` is written to the row id (`[]` for multiple); `id` is untouched (`<label for>` points at it; if missing one is made up).
- Attributes stay as drawn (the save already derived from them). Only: `title` = `validation_message`; `<select>` options are printed from the field (so a list changed on the legacy screen arrives without touching markup); initial value order: liveform -> `?value_<id>` -> guide -> folder name -> default.
- **`required` is stripped on a multi-checkbox group** (browser demands all, server says "at least one"); a single checkbox keeps it. Radio gets it on every input.
- **A rich-text textarea** (row `wysiwyg`) gets `data-pg-rich-text`, its `required` becomes `aria-required="true"`, its prefill is filtered; the page loads the editor (`custom-form-rich-text.md`).
- Nothing is appended under a designed form; the `__submit_label` token falls back to `lang('Submit')` only so old trees are not blank until their first save.
- The `custom form` branch of `get_page_content.php` searches the widget both in `pregion` and in `page_tree_json`: a visual page has no region.

Related: docs/memory/designer/custom-form-model.md
