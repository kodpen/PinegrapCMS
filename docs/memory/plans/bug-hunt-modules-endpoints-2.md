---
name: bug-hunt-modules-endpoints-2
description: Module list of plans/bug-hunt.md, part 4: root endpoint groups optimize to zone by area word, one line each with its state; single endpoints in endpoints-2b
---

## Which root endpoint groups (optimize to zone) are hunted?
- Endpoints `optimize` (2): clean (optimize, optimize_image)
- Endpoints `order` (22): partly done #5115, #5125 (order_*.php: order_form, order_history_*, order_invoice_print, order_preview, order_return); print_order #5170; retrieve_order, delete_order, connect_user_to_order, view_orders_for_contact clean; cancel_order, edit_orders, view_order_reports clean (#5178); #5202 (classic order view, receipt, preview labels through lang()), #5210 (view_orders export DISTINCT under the filter joins); #5215 (view_order offer code escaped); update_order, view_order_report, add_order, get_order_form, express_order clean; submit_order #5270 (PayPal Express token as string); still open: get_express_order
- Endpoints `page` (10): done #5135 (get_page RSS no-feed description escaped; page_view, update_view_page_mode, duplicate_page, edit_page_layout, editor_select_page_or_file clean), #5151 (get_page theme designer preview: CSS no longer HTML-escaped, theme_id scalar only; add_page, edit_page, get_page_content clean)
- Endpoints `parasut` (2), `privacy` (2): clean; `preview` (3): #6033 (style, theme: ids one value)
- Endpoints `product`, part 1 (products): done #5257 (duplicate_products: the copy's product code made unique), #5355 (duplicate_products: short description cut by letters, not bytes); add_product, edit_product, edit_products, view_products, duplicate_product, check_for_products_in_cart, get_product_group_for_product, get_erp_products, product_feed_job clean. Not covered here: barcode generation (edit_products assign_barcodes, the panel and API assigners) checks only product_barcodes, not other products' codes; the writers behind add/edit_product live in product_builder (part 3)
- Endpoints `product`, part 2 (attributes, groups): done #5393 (duplicate_product_group, Catalog Manager copy: variant set lost form template and price rules); the rest clean (option label repeats checked only in product_attribute_action)
- Endpoints `product`, part 3 (import, export, builder, marketplace): import_products: #5371 (columns after a sfc_/sfu_ heading were read one cell to the left); import_products_f, export_products, export_products_f, product_builder, marketplace_products clean
- Endpoints `recurring` (3): done #5525 (recurring_payment_job: no web gate); the commission profile screens clean
- Endpoints `referral` (3): add_referral_source, edit_referral_source, view_referral_sources clean
- Endpoints `region` (3): get_region_content, save_region_content, view_regions clean
- Endpoints `registration` (4): done #6041 (lang() labels, send_to[])
- Endpoints `remove` (3): done #6041 (cart ids[])
- Endpoints `return` (4): clean
- Endpoints `search` (5): #6064 (simple search); rest clean
- Endpoints `seo` (8): done #6091 (self link hid an orphan page); the rest clean
- Endpoints `setting` (14): clean
- Endpoints `shipping` (9): done #6086
- Endpoints `shopping` (2): #6113
- Endpoints `short` (5): clean
- Endpoints `software` (3): open
- Endpoints `style` (4): clean; lead: view_styles counts stored, not inherited, mobile ids
- Endpoints `submitted` (5): done #6098 (edit_submitted_form: one checked id); the rest clean
- Endpoints `subscription` (3): open
- Endpoints `system` (5): open
- Endpoints `theme` (6): done #4725, #4739, #4764; lead: theme_designer Cancel calls an undefined `theme_designer_cancel_confirm()`
- Endpoints `translation` (3): open
- Endpoints `user` (5): done #4489, #4505 (add_user, edit_user, delete_users, import_users, view_users)
- Endpoints `verified` (3): open
- Endpoints `view` (2): open
- Endpoints `visitor` (3): open
- Endpoints `waf` (4): done (#4601, #4620)
- Endpoints `workspace` (21): #6120 (settings: Bring it back keeps department names unique); rest clean
- Endpoints `zone` (3): open
