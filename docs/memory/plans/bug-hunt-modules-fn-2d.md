---
name: bug-hunt-modules-fn-2d
description: Module list of plans/bug-hunt.md, part 2d: the includes/fn/ modules widgets_cart and widgets_catalog, moved out of part 2b for size, one line each with its state
---

## Which includes/fn/ modules (widgets_cart, widgets_catalog) are hunted?
- `widgets_cart`: open (#5140: a cart on the home page was not recorded as the cart page; checked, clean: tax estimate, express order tax addresses, live tax fields and answer, recurring schedule and rules, tax and shipping notice, currency picker, offline payment box, pending offers, quick add, quantity box retarget, empty state, cart URL, cart link subtotal and placement ids, express order charged totals)
- `widgets_catalog`: taken by Arzu v15 worker 2 (#5134: clear filters kept the attribute filters; #5155: a % or _ in the search matched every product; #5180: a long Turkish search came back empty; #5199: a refused add showed the "added to cart" notice of an earlier add; checked, clean: RSS and JSON-LD parts, group paths and subtree, item resolution, breadcrumb rows, group tree, card templates, variant flag marks, variant attribute markers, wishlist and stock-notify controls, listing URL params, price, stock and attribute filters, sort, listing rows, group cards and pagination; still to read: the item view's binding pass and variant markers)
