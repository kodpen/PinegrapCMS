---
name: commerce-delete-confirm
description: Edit State, Shipping Method, Zone and Product Attribute name and count, in their Delete confirmation, the records the delete removes or unlinks
---

## What does each Delete confirmation name?
- Edit State: the verified shipping addresses of the state (deleted with it, rows of `view_verified_shipping_addresses.php`).
- Edit Shipping Method: its ship date adjustments (deleted) and the offer actions that discount shipping on it (unlinked, so they stop discounting).
- Edit Zone: the products and shipping methods losing the zone, then a second sentence for the shippable products and methods left with no other zone. Checkout intersects the allowed zones of the cart's products and offers a method only through its zones, so those can no longer ship.
- Edit Product Attribute: the products and product groups using it (the attribute and its options are removed from them).
- With every count 0 the old 'WARNING: This {var:1} will be permanently deleted.' stays. What a delete removes did not change. Edit Country already named its states and addresses and is the model.

## Where is it built?
- In the shared form files: `pg_*_form_delete_counts($id)` (db_q, a list of counts) and the pure `pg_*_form_delete_confirm(...counts)`, which returns a list of `lang()` argument arrays, one whole sentence each, with `{var:N}`/`{suffix:N}` (the verb "will" never agrees with a count, so no two-key pair is needed). The screen prints `h(implode(' ', array_map('lang', …)))` into `data-confirm-content`.
- Tests: `tests/commerce_delete_confirm_test.php` (keys per count combination, the screens' calls, the Turkish values).

Related: docs/memory/ui/panel-count-text.md, docs/memory/ui/state-shared-form.md, docs/memory/ui/zone-shared-form.md, docs/memory/ui/product-attribute-shared-form.md, docs/memory/ui/shipping-method-shared-form.md
