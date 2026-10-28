---
name: collection-inputs
description: Inputs of a collection's fields by kind (C3): number, image and reference controls on the site form and the panel's add/edit screen, the server checks, what is not drawn yet
---

## What does each kind draw?
- Number (text box): `type=number step=any inputmode=decimal` (`pg_coll_input_attrs()`), on the site form (`pg_cf_apply_field_bindings()`) and on Add / Edit Submitted Form (`pg_submitted_form_fields_markup()`).
- Image (file upload): `accept` of JPG, PNG, GIF and WebP on the site form (`pg_coll_image_types()`; SVG left out, it can carry script). The panel's file control cannot print `accept` (liveform); the server check covers it.
- Reference (pick list): the complete records of the form it points into, code => Title (else the code), at most 500, sorted (`pg_coll_reference_options()`). A label two records share gets the code after it (`pg_coll_reference_labels()`): the panel pick list is keyed by labels. The site keeps the designer's empty-valued prompt; the panel adds an empty choice. A reference whose form is gone (`reference_page_id` 0) keeps its drawn choices.
- `pg_cf_field_map()` reads `collection_kind` and `reference_page_id` (probed with `pg_coll_ready()`).

## What does the server check?
- `pg_coll_check_field()` after a field's own checks, in `custom_form.php` (site) and `pg_submitted_form_check_field()` (panel add and edit), kinds from `pg_coll_field_kinds()`.
- Number: `pg_coll_number_value()`; a decimal comma is stored as a point, `+` dropped, `.5` → `0.5`; thousands separators, exponents and over 30 characters are refused, so C4 can sort the stored text as a number.
- Image: the file name's extension (`pg_coll_image_name_ok()`).
- Reference: every code (one, comma-joined from a text box, or a list) must be a record code of the target form (`pg_coll_reference_ok()`); a text box or pick list only.

## What is not drawn yet?
- Radio button and check box references stay the designer's drawn choices, on the site and in the panel, and the server does not hold them to the records: checking would refuse every submission until they are drawn from records.
- Text box references stay text (several codes joined by commas).
- Image preview on the form, `pg_img_responsive_html()` on output, product and page pickers: see `plans/designer-collections-steps.md`.

Tests: `tests/collections_inputs_test.php`.
