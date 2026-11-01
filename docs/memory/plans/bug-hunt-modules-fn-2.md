---
name: bug-hunt-modules-fn-2
description: Module list of plans/bug-hunt.md, part 2: includes/fn/ modules price_history to wishlist, one line each with its state
---

## Which includes/fn/ modules (price_history to wishlist) are hunted?
- `price_history`: clean

- `privacy`: clean

- `privacy_export`: taken by LA, #4589

- `privacy_retention`: taken by LV, #4680

- `product_customs`: open

- `product_feeds`: open

- `product_list`: open

- `prototype`: open

- `prototype_links`: open

- `qr`: open

- `record_revisions`: open

- `returns`: clean (#4619)

- `reviews`: taken by LC, #4597

- `search_console`: open

- `seo`: open

- `shipment_labels`: open

- `shipment_lookup`: open

- `shipment_tracking`: open

- `shipping`: taken by LL, #4625

- `shipping_import`: open

- `short_link_redirects`: open

- `signature`: open

- `signin`: clean

- `site_datetime`: open

- `site_icon`: open

- `site_log_list`: open

- `site_search`: taken by LX, #4678

- `site_time_sql`: open

- `sms`: #4674 (LW; rest clean)

- `sms_campaigns`: taken by MC, #4707

- `sms_codes`: clean (#4614)

- `sms_messages`: open

- `speculation`: open

- `stock_notify`: #4668

- `submitted_form_list`: open

- `subscription_renewal`: fault fixed by LN, #4622 (renewals stop at the line's number of payments, decided in #4623)

- `subscriptions`: clean (#4621)

- `subscriptions_account`: open

- `subscriptions_operator`: open

- `system_status`: open

- `text_search`: open

- `tour`: open

- `translate`: open

- `update`: open

- `user_list`: open

- `vendor_api`: open

- `visitor_ip`: open

- `visitor_partitions`: open

- `visitor_retention`: open

- `visitor_texts`: open

- `voice_text`: open

- `waf_log_list`: open

- `waf_notice`: open

- `waf_rescue`: open

- `widgets`: open

- `widgets_account`: open

- `widgets_ad_region`: open

- `widgets_affiliate_sign_up`: open

- `widgets_attrs`: open

- `widgets_billing_information`: open

- `widgets_cart`: open

- `widgets_catalog`: open

- `widgets_comments`: open

- `widgets_comments_recent`: open

- `widgets_conditions`: open

- `widgets_entity_fields`: open

- `widgets_error`: open

- `widgets_express_order`: open

- `widgets_fill`: open

- `widgets_finish`: open

- `widgets_folder_view`: open

- `widgets_gallery`: open

- `widgets_google_reviews`: open

- `widgets_language`: open

- `widgets_lists`: open

- `widgets_lists_calendar`: open

- `widgets_lists_cart`: open

- `widgets_lists_checkout`: open

- `widgets_lists_menu`: open

- `widgets_lists_order`: open

- `widgets_lists_paging`: open

- `widgets_lists_product`: open

- `widgets_menu`: open

- `widgets_order_form`: open

- `widgets_order_preview`: open

- `widgets_parts`: open

- `widgets_parts_anywhere`: open

- `widgets_parts_forms`: open

- `widgets_photo_gallery`: open

- `widgets_placement`: open

- `widgets_shipping_address`: open

- `widgets_shipping_method`: clean (refusals, methods, amounts, remove link)

- `widgets_starters`: clean (#4628)

- `widgets_tag_cloud`: clean (classic and Search Results widget keyword reads against the classic sync, merge and case folding, size and weight scale, token escaping and search links checked)

- `widgets_wishlist`: clean (owner-only list read, background renders, token escaping and raw price keys, row action wiring and hidden forms, stock and discount rules against the catalog cards, shop and login links checked)

- `wishlist`: clean (wishlist.php token, method and throttle checks, cookie hashing and merge, owner SQL, trim batches, page script and its injection checked)
