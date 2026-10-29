---
name: order-view-picker-links
description: Order view product name and image are real links to edit_product.php; the editor's Browse Server link picker rows hold a button; its type icons are aria-hidden
---

## How does the order view link a product?
- In both item blocks of `view_order.php` (shippable and not), the "Product ID / SKU: …" heading is `<a href="edit_product.php?id=N">` and the product image sits inside the same link. Both were `onclick` on a span and an img, out of the keyboard's reach (#2960).
- The image link has `tabindex="-1" aria-hidden="true"` and the image `alt=""`: the heading link already names the target, so the image adds no second, unnamed tab stop. The sticky style moved from the img to the link.

## How is a link chosen from the keyboard in the link picker?
- `editor_select_page_or_file.php` is CKEditor's "Browse Server" window (`filebrowserBrowseUrl` in `includes/fn/editor.php`). Each page, file and short link row's name cell holds `<button type="button" class="btn btn-link p-0 text-start">` that runs the same `CKEDITOR.tools.callFunction(...)` and `window.close()` as the row. It calls `event.stopPropagation()` first so the row's own click does not run the call twice. The row click stays for the mouse.
- The Pages, Files and Short Links buttons' ligatures (`desktop_windows`, `insert_drive_file`, `link`) are `aria-hidden`, so the buttons read only their words.

## What was not checked?
- Nothing was rendered in a browser. Test: `tests/order_view_picker_links_test.php`.

Related: docs/memory/ui/dashboard-keyboard.md, docs/memory/ui/icon-ligature-button-names.md
