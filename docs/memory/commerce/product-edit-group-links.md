---
name: product-edit-group-links
description: Edit Product save writes only the product groups added or taken off, so each group link keeps its sort order, featured mark and new date
---

## What does an Edit Product save write to products_groups_xref?

Only the difference: `pg_pb_group_links_change()` compares the product's
stored groups (`pg_pb_product_group_ids()`) with the posted
`catalog_group_ids`; rows of groups taken off are deleted, a bare row is
inserted for each group added, and the rows it stays in are left alone.

## Why not delete and insert every row?

A link row holds what other screens set: the product's sort order on Edit
Product Group, and the featured mark, featured sort order and new date on
Featured and New Items. Deleting every row on save put them back to their
defaults, so a product saved with no change fell to the top of each group
and dropped out of Featured and New Items (#10368).

## Does the API do the same?

Not yet: `PATCH /products/{id}` with `group_ids`
(`includes/api/resources/products.php`) still deletes and inserts every
row (#10364).
