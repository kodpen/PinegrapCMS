---
name: bug-hunt-modules-endpoints-2b
description: Module list of plans/bug-hunt.md, part 5: the single root endpoints (one file per area word), six to a line, each line with its state
---

## Which single endpoints are hunted?
- Done: view_accessibility, update_address_book, allow_or_disallow_new_comments, approve_affiliate, view_audit_log, backups #5130, browse_catalog_menu, cancel_order, carrier_webhook, chat, clean_up #5142, clear_theme_designer_session, cloudflare #5144 #5161, confirm_subscription, connected_apps, connections, consent_answer, view_countries, create_file, get_cross_sell_items, csp_report, view_currencies, database_engine, developer_lock #5166, do #5174, excel_reader2, update_exchange_rates, edit_featured_and_new_items, feed, functions, health, get_help_url, update_imported_items #5121, index, init, integration, iys_job, edit_javascript, job #5074, kiosk #5110, legal_document, liveform.class, get_llms.txt, local_sale, view_log, mailchimp, mass_edit #4987 #5051, mcp, mfa, migration, myself_upsell, mysqldump, view_not_found_log, oauth, add_or_remove_watcher #5015, organize_contacts, print_packing_slip, passkey, set_password, payment_callback, pclzip.lib, view_performance_log, get_photo_gallery, pi, private_label, prototype #4773, purge_cache, push_job, reload_theme, reserve_calendar_event, reset_password, retrieve_order #4672 #4691 #4740, get_robots.txt, router, send_email_campaign, view_sessions, get_set_password, shipworks #4655, si, signature_receipt, signin, get_site_icon, get_sitemap_info, get_sitemap.xml #4758, smtp_settings, stock_notify, submit_order, test_secure_mode, add_to_cart, toolbar #4600 #4607 #4618, track_shipment, get_update_address_book #4604, url_to_absolute, variant_set_action, welcome, wishlist, import_zip #4640, get_shopping_cart #6157
- Not a fault: `do.php?action=add_watcher_for_product_submit_form` sets the watcher from any link, by design.

## Which groups outside the root and fn lists are hunted?
- Dashboard v2 (`includes/dashboard/v2/`, its actions in `includes/panel/dashboard.php`, `pg_dash*.js`): done #7003.
