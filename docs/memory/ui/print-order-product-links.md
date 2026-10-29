---
name: print-order-product-links
description: The order print view's product name and image are real links to edit_product.php, reachable from the keyboard
---

## How does the order print view link a product?
- As the order view does (`order-view-picker-links.md`, #2960): in both item blocks of `print_order.php` the product name is `<a href="edit_product.php?id=N" class="pg-link-body-emphasis text-decoration-none">`, body ink so it prints as text. Both were `onclick` on a span and an img, out of the keyboard's reach.
- The image sits in a link with `tabindex="-1" aria-hidden="true"` and `alt=""`: the name link already names the target. The sticky style and `pg-print-none` moved from the img to the link.

## How is it checked?
- `tests/print_order_product_links_test.php` (source pins). Nothing was rendered in a browser.

Related: docs/memory/ui/order-view-picker-links.md, docs/memory/ui/print-views-light-paper.md
