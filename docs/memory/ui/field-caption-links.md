---
name: field-caption-links
description: Captions tied to their fields on the code-field screens, Generate Layout, Create File and the order tracking numbers; why chips need a label, not an aria-label
---

## Why does a code field need a label for?
- The code editor names its content from the textarea (`pgCeTextareaName()` in `pg_code_editor.js`): its `aria-label`, else `aria-labelledby`, else its first `<label>`. A caption printed before `div#edit_custom` without `for` reaches nothing, and `pgInjectAriaAndRequiredMarkers()` only looks at a label's next siblings, so the editor these screens exist for was unnamed.
- `edit_theme_css.php`, `edit_javascript.php` and `edit_file.php` print `<label for="code">`. Generate Layout (`includes/templates/generate_layout.php`) has no visible caption; its read-only `#layout` textarea carries `aria-label` Layout.

## How is Create File named?
- `create_file.php`: the name box and the extension select sit in one input group under "Main Informations" with no caption; they carry `aria-label` File Name and File Type.

## Why is "Tracking Numbers:" a label on the order view?
- `view_order.php` prints a chips field `ship_to_id_tracking_numbers_<ship_to_id>` per address on a complete order. `drawChips()` (`pg_fields.js`) names the typed box only from the hidden input's `el.labels`, so the caption is a `<label for>` that field's id; an `aria-label` would not reach the box. Without the field (order not complete) the caption stays a plain span.

## Which related fields are named elsewhere?
- Submitted form pick lists, file uploads and choice groups: `submitted-form-shared-form.md`. Calendar event "Up To": `calendar-event-shared-form.md`.
- Tests: `tests/field_caption_links_test.php` (source scans).
