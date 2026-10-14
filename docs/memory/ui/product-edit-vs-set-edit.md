---
name: product-edit-vs-set-edit
description: Why product editing (edit_product.php) and variant set editing (edit_product_group.php) are separate screens, and why the product screen has no variant matrix.
---

## Which screen edits what?
| From | Button | To |
|---|---|---|
| All Products list | Düzenle | `edit_product.php?id=N`: ONE product, new interface |
| Variant Sets list | Düzenle | `edit_product_group.php?id=N`: the group; links to its products |

## Why does the product edit screen have no variant matrix?
- The matrix exists only when ADDING a product and on the group screen. The reason is structure, not a ban: with the matrix on the single-product screen, an operator who entered to fix one variant could add a new variant under it, and a variant of a variant does not exist. The control is simply absent there, so the state is impossible and no rule is needed.

## Is a product second-class because it belongs to a group?
- No. The same product can be a variant in one group and a plain member of another (`products_groups_xref` allows multiple membership). So the product must have its own detailed edit screen; the assumption "it is edited from the set screen anyway" is wrong.
