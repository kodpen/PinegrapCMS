---
name: bug-hunt-modules-endpoints-2
description: Module list of plans/bug-hunt.md, part 4: root endpoint groups optimize to zone by area word and the single endpoints, one line each with its state
---

## Which root endpoint groups (optimize to zone) and single endpoints are hunted?
- Endpoints `optimize` (2): open

- Endpoints `order` (22): open

- Endpoints `page` (10): open

- Endpoints `parasut` (2): open

- Endpoints `preview` (3): open

- Endpoints `privacy` (2): open

- Endpoints `product` (22): open

- Endpoints `recurring` (3): open

- Endpoints `referral` (3): open

- Endpoints `region` (3): open

- Endpoints `registration` (4): open

- Endpoints `remove` (3): open

- Endpoints `return` (4): open

- Endpoints `search` (3): open

- Endpoints `seo` (8): open

- Endpoints `setting` (14): open

- Endpoints `ship` (3): open

- Endpoints `shipment` (3): open

- Endpoints `shipping` (9): open

- Endpoints `shopping` (2): open

- Endpoints `short` (5): open

- Endpoints `sms` (3): open

- Endpoints `software` (3): open

- Endpoints `state` (3): open

- Endpoints `style` (4): open

- Endpoints `submitted` (5): open

- Endpoints `subscription` (3): open

- Endpoints `system` (5): open

- Endpoints `tax` (3): open

- Endpoints `theme` (6): done #4725, #4739, #4764; lead: theme_designer Cancel calls an undefined `theme_designer_cancel_confirm()`

- Endpoints `translation` (3): open

- Endpoints `user` (5): done #4489, #4505 (add_user, edit_user, delete_users, import_users, view_users)

- Endpoints `verified` (3): open

- Endpoints `view` (2): open

- Endpoints `visitor` (3): open

- Endpoints `waf` (4: view_waf_log, waf, waf_ranges_job, waf_unban): done (#4601, #4620)

- Endpoints `workspace` (21): open

- Endpoints `zone` (3): open

- Endpoints view_accessibility, update_address_book, allow_or_disallow_new_comments, approve_affiliate, view_audit_log, backups: open

- Endpoints browse_catalog_menu, cancel_order, carrier_webhook, chat, clean_up, clear_theme_designer_session: open

- Endpoints cloudflare, confirm_subscription, connected_apps, connections, consent_answer, view_countries: open

- Endpoints create_file, get_cross_sell_items, csp_report, view_currencies, database_engine, developer_lock: open

- Endpoints do, excel_reader2, update_exchange_rates, edit_featured_and_new_items, feed, functions: open

- Endpoints health, get_help_url, update_imported_items, index, init, integration: open

- Endpoints iys_job, edit_javascript, job, kiosk, legal_document, liveform.class: open

- Endpoints get_llms.txt, local_sale, view_log, mailchimp, mass_edit, mcp: open

- Endpoints mfa, migration, myself_upsell, mysqldump, view_not_found_log, oauth: open

- Endpoints add_or_remove_watcher, organize_contacts, print_packing_slip, passkey, set_password, payment_callback: open

- Endpoints pclzip.lib, view_performance_log, get_photo_gallery, pi, private_label, prototype: done #4773 (pclzip.lib bundled, rest clean)

- Endpoints purge_cache, push_job, reload_theme, reserve_calendar_event, reset_password, retrieve_order: done (#4672, #4691, #4740)

- Endpoints get_robots.txt, router, send_email_campaign, view_sessions, get_set_password, shipworks: done (#4655)

- Endpoints si, signature_receipt, signin, get_site_icon, get_sitemap_info, get_sitemap.xml: done #4758 (rest clean)

- Endpoints smtp_settings, stock_notify, submit_order, test_secure_mode, add_to_cart, toolbar: done #4600, #4607, #4618 (stock_notify, test_secure_mode, toolbar: nothing found)

- Endpoints track_shipment, get_update_address_book, url_to_absolute, variant_set_action, welcome, wishlist: done (#4604: get_update_address_book array id and missing row; track_shipment token and throttle, url_to_absolute RFC 3986 cases, variant_set_action set check, welcome role ladder and output, wishlist token and redirect checked)

- Endpoints import_zip: done (#4640: page title over several lines or with attributes; upload refusal, archive ceilings, blocked names, entry paths, escaping of skipped names, CSRF and area gate checked)
