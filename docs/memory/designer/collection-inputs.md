---
name: collection-inputs
description: Inputs of a collection's fields by kind (C3, C3b, C3c): number, image, reference, product and page controls on the site form and the panel, radios and boxes, image preview, server checks
---

## What does each kind draw?
- Number (text box): `type=number step=any inputmode=decimal` (`pg_coll_input_attrs()`), on the site form (`pg_cf_apply_field_bindings()`) and on Add / Edit Submitted Form (`pg_submitted_form_fields_markup()`).
- Image (file upload): `accept` of JPG, PNG, GIF and WebP on the site form (`pg_coll_image_types()`; SVG left out, it can carry script) and `data-pg-image-preview`: `pg_cf_validation_script()` shows the chosen file below the control (object URL, `img-thumbnail`). The panel's file control cannot print `accept` (liveform); the server check covers it. Edit Submitted Form shows the stored picture above the file's link.
- Reference (pick list): the complete records of the form it points into, code => Title (else the code), at most 500, sorted (`pg_coll_reference_options()`). A label two records share gets the code after it (`pg_coll_reference_labels()`): the panel pick list is keyed by labels. The site keeps the designer's empty-valued prompt; the panel adds an empty choice. A reference whose form is gone (`reference_page_id` 0) keeps its drawn choices (`pg_cf_reference_choices_for()` null).
- Reference (radio button, check box): on the site `pg_cf_reference_choice_nodes()` runs on each element's children before its controls are bound: the group's first option is the template, repeated once per record in place of every drawn option. An option is a wrapper holding the box, one label and inline text only (`div.form-check`), or a bare box plus the label right after it; a wrapper with a legend or the question's label is looked into instead. Each box gets the code, an id of its own (`<drawn id>_r<n>`, else `cf_<field id>_r<n>`), no drawn `checked`, and `_pg_cf_ref_drawn` so the walk one level down does not draw it again; its label takes the record's label and points at it. No records: no option at all (a drawn value would be refused). A check box group's `required` counts the records. The panel draws the records as its radios / boxes (no empty choice); a reference check box group always posts an array.
- Product, page (pick list only, C3c, #2814): the value is the id. Products: enabled, not in the recycle bin, label short description else name (`pg_coll_product_options()`); pages: folder not archived nor binned, `check_view_access()` (`pg_coll_page_options()`); at most 500, most recent, through `pg_coll_pick_options()` (labels as references). `pg_coll_kind_options()` gives every kind's choices; `pg_cf_reference_choices_for()` and the panel read it.
- `pg_cf_field_map()` reads `collection_kind` and `reference_page_id` (probed with `pg_coll_ready()`).

## What does the server check?
- `pg_coll_check_field()` after a field's own checks, in `custom_form.php` (site) and `pg_submitted_form_check_field()` (panel add and edit), kinds from `pg_coll_field_kinds()`.
- Number: `pg_coll_number_value()`; a decimal comma is stored as a point, `+` dropped, `.5` → `0.5`; thousands separators, exponents and over 30 characters are refused, so C4 can sort the stored text as a number.
- Image: the file name's extension (`pg_coll_image_name_ok()`).
- Reference, product, page: every value (one, comma-joined from a text box, or a list) must be one of `pg_coll_kind_options()` (`pg_coll_reference_ok()`), so a product or page the visitor may not see is refused; a record already holding one that left the list must be given another on Edit.

## What is not drawn yet?
- Text box references stay text (several codes joined by commas).
- Product / page tokens (name, link, price, image) on a detail page: C4b (`plans/designer-collections-steps.md`).

Tests: `tests/collections_inputs_test.php`.
