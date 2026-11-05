---
name: list-table-quick-edit-product-attributes
description: Product Attributes list quick edit (view_product_attributes.php, commerce_list.php): name and label change in place, the options stay text
---

## Which cells change?
- Name and Label: `pg_product_attribute_edit_columns()`, written by `pg_product_attribute_quick_update()`. These are the only values of the attribute row itself that `edit_product_attribute.php` changes, so the list covers the screen.

## Which stay text, and why?
- Options: an ordered list of several options, each with its own label and 'No Thanks' flag; the driver has no input for that, so they change on the attribute's screen (as the contact groups do on the contact's).
- Created and Last Modified: computed. A saved name or label sends the Last Modified cell back too.

## Are the checks the screen's checks?
- Yes. Name required, at most 100 characters, and not used by another attribute (the screen's message); label at most 255 (`pg_product_attribute_form_check()` on the screen). Rights: role below User or `manage_ecommerce`, as `validate_ecommerce_access()`.
