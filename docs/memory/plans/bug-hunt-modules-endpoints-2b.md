---
name: bug-hunt-modules-endpoints-2b
description: Module list of plans/bug-hunt.md, part 5: the single root endpoints (one file per area word), six to a line, each line with its state
---

## Which single endpoints are hunted?
- Endpoints view_accessibility, update_address_book, allow_or_disallow_new_comments, approve_affiliate, view_audit_log, backups: open

- Endpoints browse_catalog_menu, cancel_order, carrier_webhook, chat, clean_up, clear_theme_designer_session: open

- Endpoints cloudflare, confirm_subscription, connected_apps, connections, consent_answer, view_countries: open

- Endpoints create_file, get_cross_sell_items, csp_report, view_currencies, database_engine, developer_lock: open

- Endpoints do, excel_reader2, update_exchange_rates, edit_featured_and_new_items, feed, functions: open

- Endpoints health, get_help_url, update_imported_items, index, init, integration: open

- Endpoints iys_job, edit_javascript, job, kiosk, legal_document, liveform.class: open

- Endpoints get_llms.txt, local_sale, view_log, mailchimp, mass_edit, mcp: mass_edit done #4987; rest taken by a worker of Arzu

- Endpoints mfa, migration, myself_upsell, mysqldump, view_not_found_log, oauth: clean (mysqldump bundled)

- Endpoints add_or_remove_watcher, organize_contacts, print_packing_slip, passkey, set_password, payment_callback: done #5015 (add_or_remove_watcher: submission without an e-mail field matched a member with none; rest clean)

- Endpoints pclzip.lib, view_performance_log, get_photo_gallery, pi, private_label, prototype: done #4773 (pclzip.lib bundled, rest clean)

- Endpoints purge_cache, push_job, reload_theme, reserve_calendar_event, reset_password, retrieve_order: done (#4672, #4691, #4740)

- Endpoints get_robots.txt, router, send_email_campaign, view_sessions, get_set_password, shipworks: done (#4655)

- Endpoints si, signature_receipt, signin, get_site_icon, get_sitemap_info, get_sitemap.xml: done #4758 (rest clean)

- Endpoints smtp_settings, stock_notify, submit_order, test_secure_mode, add_to_cart, toolbar: done #4600, #4607, #4618 (stock_notify, test_secure_mode, toolbar: nothing found)

- Endpoints track_shipment, get_update_address_book, url_to_absolute, variant_set_action, welcome, wishlist: done (#4604: get_update_address_book array id and missing row; track_shipment token and throttle, url_to_absolute RFC 3986 cases, variant_set_action set check, welcome role ladder and output, wishlist token and redirect checked)

- Endpoints import_zip: done (#4640: page title over several lines or with attributes; upload refusal, archive ceilings, blocked names, entry paths, escaping of skipped names, CSRF and area gate checked)
