---
name: order-view-picker-links
description: Order view product name and image are real links to edit_product.php, reached from the keyboard
---

## How does the order view link a product?
- In both item blocks of `view_order.php` (shippable and not), the "Product ID / SKU: …" heading is `<a href="edit_product.php?id=N">` and the product image sits inside the same link. Both were `onclick` on a span and an img, out of the keyboard's reach (#2960).
- The image link has `tabindex="-1" aria-hidden="true"` and the image `alt=""`: the heading link already names the target, so the image adds no second, unnamed tab stop. The sticky style moved from the img to the link.

## What was not checked?
- Nothing was rendered in a browser. Test: `tests/order_view_picker_links_test.php`.

Related: docs/memory/ui/dashboard-keyboard.md, docs/memory/ui/icon-ligature-button-names.md
