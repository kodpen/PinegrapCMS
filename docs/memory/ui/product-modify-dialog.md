---
name: product-modify-dialog
description: Products list and product group Modify Selected is an in-page pgUI dialog (no popup); its fields belong to the page form, edit_products.php only takes the post
module: product_list
---

## How does Modify Selected on the products list work?
- `pg_product_modify_dialog($form_id, $warning = '')` (`includes/fn/product_list.php`) prints `includes/templates/edit_products.php`: a `<dialog id="product_modify_dialog">` whose fields carry `form="<form_id>"` and the post names `edit_*` directly, so they post with that form. Only the zone check boxes are unowned; Save joins them into `edit_allowed_zones` / `edit_disallowed_zones`.
- A button with `data-pg-modify-open` opens it empty (selects reset, boxes cleared). Save checks the tax rate box (0..100 when set) and the stock figure, sets `action=edit` and submits in `pgUI.dialog.hide(dialog).then(...)` (`dialog-result-after-close.md`). Assign Barcodes (when `BARCODE_ENABLED`) sends `action=assign_barcodes` the same way.
- `view_products.php` prints it with `view_products_form`, the list form that carries the selection.
- `edit_product_group.php` has its own `#product_group_modify_form` (token, action, selection, `list_params` filter `all_products`), outside the group form; its bar button fills `selection` with `{ids: [...]}` from the ticked `products[]` boxes, and the dialog shows the "changes in this product group will disappear" warning. Before, the group posted its whole form to `edit_products.php` without a `selection`, so no product was changed.
- `edit_products.php` on GET redirects to `view_products.php`.

## Why not the popup any more?
- It was a 500x500 `window.open()` writing into `opener.document.form` (#6523): stopped by popup blockers, a new tab on phones, no focus trap; its template kept a hidden theme menu, Bootstrap 3 `col-xs-12`, popover markup as boxes and fixed `lg:col-3` columns that cut the option texts.

## How does Delete Selected ask?
- With `pgConfirm()`, titled and confirmed with the button's own label, in red. The `data-confirm-content` click handler in `backend.src.js` sends every bulk action that posts `document.form` itself (Delete, Cancel, Mark Shipped, Merge, Opt-In/Opt-Out, Remove Card Data Selected) through `pgConfirm` and submits on yes; a no clears `action`. Links, `data-confirm-action` buttons and the Paraşüt export keep `window.confirm()`, which answers synchronously. Before, Delete Selected used the browser's own box (#6523).

## Where is it tested?
- `tests/bulk_popup_texts_test.php` (`test_bulk_popup_texts_modify_products_is_a_dialog`), plus the field pins in `tests/modify_selected_value_names_test.php`, `tests/locale_amount_inputs_test.php`, `tests/explorer_handle_products_bulk_test.php`.

Related: docs/memory/ui/design-files-modify-dialog.md, docs/memory/ui/contacts-organize-dialog.md, docs/memory/ui/page-bulk-edit-dialog.md
