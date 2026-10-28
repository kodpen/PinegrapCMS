---
name: product-attribute-shared-form
description: The product attribute create and edit screens share includes/forms/product_attribute_form.php: what it holds, what stays in the screens
---

## Where is the product attribute form?
- `includes/forms/product_attribute_form.php` (gate `PG_FUNCTIONS_DIR`). `add_product_attribute.php` and `edit_product_attribute.php` stay at the root as thin wrappers and `require_once` it.
- `pg_product_attribute_form_cards($liveform)` prints the Main Informations card (name, label) and the Options card with its `init_product_attribute_options()` script.
- `…_output_options($options)` is the list that script starts with: the posted or stored JSON, or two empty options.

## What does a save share?
- `pg_product_attribute_form_check($liveform, $id)` (`null` on create, the posted id on edit) marks a name another attribute uses and an empty option list, and returns the posted options. The name check runs through `db_q()`.
- The required check stays in each screen, because create and edit word it differently ("Name is required" on edit, "{var:1} is required" on create).

## What stays in the screens?
- Create: inserts the attribute, then its options in order.
- Edit: the not-found guard, the record and option load, delete (options and both xref tables), the UPDATE. Options are updated by id, added when new, and removed (with their product links) when left out. These option queries are still raw `db()` calls.
- Tests: `tests/product_attribute_form_test.php`.
