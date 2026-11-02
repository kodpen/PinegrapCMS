---
name: bug-hunt-modules-fn-2c
description: Module list of plans/bug-hunt.md, part 2c: includes/fn/ modules widgets_lists_menu to wishlist, one line each with its state
---

## Which includes/fn/ modules (widgets_lists_menu to wishlist) are hunted?
- `widgets_lists_menu`: clean (access, current and holding items, addresses and window as the classic menu)
- `widgets_lists_order`: clean (form answers, gift cards, return rows escaping)
- `widgets_lists_paging`: clean (window and clamped page key)
- `widgets_lists_product`: clean (images, attributes, bundle rows, crumbs, filter chips)
- `widgets_menu`: done (#4999: placed twice, shared row ids; rest clean: classic menu and sequence regions, access, edit chip)
- `widgets_order_form`: clean
- `widgets_order_preview`: clean (flags, method rows and On Account, page row changes, recipients and gift card markup escaping, tokens, form wrapper checked)
- `widgets_parts`: clean (`commerce/cart-product-form-file-field.md`)
- `widgets_parts_anywhere`: clean (marker encode and decode, read-only refusals, injection into the main loop, span take and place counting checked)
- `widgets_parts_forms`: clean (cancel and return forms against cancel_order.php and order_return.php fields and limits, restored controls, label pairing, cart product form attributes checked)
- `widgets_photo_gallery`: done (#4754)
- `widgets_placement`: done (#4736)
- `widgets_shipping_address`: clean (#4727)
- `widgets_shipping_method`: clean (refusals, methods, amounts, remove link)
- `widgets_starters`: clean (#4628)
- `widgets_tag_cloud`: done (#4999: placed twice, shared row ids; rest clean: classic and Search Results widget keyword reads against the classic sync, merge and case folding, size and weight scale, token escaping and search links checked)
- `widgets_wishlist`: clean (owner-only read, escaping, row actions, stock and discount rules checked)
- `wishlist`: clean (wishlist.php token, method and throttle checks, cookie hashing and merge, owner SQL, trim batches, page script and its injection checked)
