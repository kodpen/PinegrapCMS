---
name: product-screen-save-checks
description: Product add/edit save checks (counts, weight, quantity range, variant set clash, unticked attributes, BCC), matrix esc(), long catalog names, duplicate options, binned duplicate
---

## Which numbers does the product save refuse?

`pg_pb_validate_product_post()` calls `pg_pb_number_fields_problem()` and
stops with a sentence naming the field. Count boxes (payments, start, membership
and access days, reward points, default/min/max quantity, inventory quantity,
primary/secondary weight points, preparation time) go through
`pg_pb_whole_number_from_input()`: digits or groups of three ("1.200" in
Turkish); a sign, decimal or letter is refused, and so is a figure above
4294967295 (their int(10) unsigned columns cut it). `-2` payments was stored
as 0 (#5269); weight points and preparation time went in raw (`-4`, `abc` as
0). `pg_pb_common_from_post()` stores the parsed number (empty points or
preparation time as 0); `pg_pb_posted_stock_quantity()` reads the stock box.
Label Column Width is empty, 0 (an old stored value, read as none) or up to
100. Price, extra shipping cost, weight and dimensions with a minus sign are
refused: their readers dropped the sign and stored it positive. Weight and
dimensions: digits and separators only, at most 99999999.9999 as pounds or
inches (`pg_pb_measure_from_input()`); `1e5` was stored as 15 and a big
figure cut to the column's top (#6527).

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
A member whose every option was unticked is refused: it would stay in the
set with no combination.

## What does unticking every option on the edit screen save?

No option ticked posts `variants_json` `[]`; the script also posts
`pg_pb_attributes_posted=1`, and only with both (`pg_pb_attributes_cleared()`)
does `pg_pb_update_product()` delete the product's `products_attributes_xref`
rows; before, the last option came back ticked (#6527).

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
screens do (#5273). `create_option` (the card's inline add) adds
`pg_product_attribute_form_label_taken()` to its SQL `=`, whose collation
tells `I` from `ı` (#6527).

## Can a binned product be duplicated?

No: `duplicate_product.php` refuses a product in the Recycle Bin and Edit
Product leaves Duplicate out of its toolbar; the copy came out live (#6527).
