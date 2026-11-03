---
name: product-attribute-options-save
description: How the product attribute create/edit screens clean, check and save options, and what removing one resets
---

## Which options are saved?

`pg_product_attribute_form_check()` (`includes/forms/product_attribute_form.php`)
returns `pg_product_attribute_form_clean_options()`: labels trimmed, rows with
no label dropped (the form opens with two empty rows), `id` an int, `no_value`
`'1'` or `''`. No option left gives "Please add an option."; a label given twice
(case ignored, Turkish `İ` folded to `i`) gives "The option {var:1} is entered
more than once." Before this the blank second row and repeated labels were
stored (#5450).

## How does the options list come back after an error?

`pg_product_attribute_form_output_options()` decodes the posted or stored JSON,
keeps only `id`/`label`/`no_value` and prints it with `JSON_HEX_TAG|AMP|APOS|QUOT`.
The raw posted JSON used to be printed into the `<script>`: a label holding
`</script>` broke it and the options were lost (#5451).

## What does Save on the edit screen touch?

Only this attribute's options: an update is `WHERE id = ? AND
product_attribute_id = ?`, and a posted id that is not one of the attribute's
stored options is inserted as a new option. A removed option loses its
`products_attributes_xref` rows and every `product_groups_attributes_xref`
`default_option_id` pointing at it becomes `0`, as the external API does
(#5453). The Site Log line names the new name, `old → new` when it changed
(#5454).

## How does the list show long names?

`view_product_attributes.php` wraps name and label in `text-wrap text-break`
at `max-width:20rem`; the Options menu is capped at `min(20rem, 90vw)` and its
items are wrapping text, not `badge` (#5452).
