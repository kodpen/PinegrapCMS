---
name: product-screen-save-checks
description: Product add/edit save checks (count boxes, quantity range, variant set clash, BCC), the matrix esc(), long catalog names, duplicate options in the new-attribute dialog
---

## Which numbers does the product save refuse?

`pg_pb_validate_product_post()` calls `pg_pb_number_fields_problem($_POST, pg_pb_thousands_separator())`
(`product_builder.php`) and stops with a sentence naming the field. Count
boxes (payments, start days, membership days, access days, reward points,
default/min/max quantity) go through `pg_pb_whole_number_from_input()`:
digits, or groups of three with the panel's thousands separator ("1.200" in
Turkish) or a space; a sign, a decimal part or a letter is refused. Before
this `-2` payments was stored as 0 (no end to the charges), `12,5` as 12 and
`abc` as is (#5269). `pg_pb_common_from_post()` stores the parsed number.
Label Column Width is empty, 0 (an old stored value, read as none) or up to
100. Price, extra shipping cost, weight and dimensions with a minus sign are
refused: their readers dropped the sign and stored it positive.

## Why are the quantities checked against each other?

With a maximum above 0, the minimum cannot exceed it and a filled default
must lie between them. `check_quantity()` applies the two limits one after
the other, so min 10 / max 2 moved the cart line between 10 and 2 on every
check (#5271).

## What does editing one member of a variant set check?

`pg_pb_variant_set_problem()` → `pg_pb_variant_set_clash()`: when the posted
attribute choice changed, every attribute must be one of the set's
(`product_groups_attributes_xref`, when it names any) and no other member may
hold the same combination. Two members of one combination cannot be told
apart by the picker and the replaced one could no longer be bought (#5272).
An unchanged choice is not checked, so a set holding an old clash still saves.

## Which BCC message is shown?

Each address names its own field, the same on add and edit: "The order
receipt bcc e-mail address is invalid." / "The BCC e-mail address is
invalid." `add_product.php` no longer repeats the check (#5276).

## How are matrix cells escaped?

`esc()` in `assets/js/product_builder.js` replaces `& < > " '`; it writes
option labels into `value=""` and `aria-label=""`. The jQuery text/html trick
it used left quotes alone: a label `Z" onfocus="…` became an attribute and
`Mavi "q"` cut the SKU (#5268).

## How long can a catalog name be?

`pg_catalog_address_slug()` cuts to 245 characters before the uniqueness
check, leaving room for "[n]" in `address_name` varchar(255); the box has
`maxlength="255"`. Cut later by the column, three products shared one
address (#5274).

## Duplicate options in the new-attribute dialog?

`create_attribute` in `product_attribute_action.php` refuses a label given
twice with `pg_product_attribute_form_duplicate_label()`, as the attribute
screens do (#5273).
