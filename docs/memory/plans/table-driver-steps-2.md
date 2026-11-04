---
name: table-driver-steps-2
description: Steps of the table driver plan, part 2: the browser mode, the printed lists in batches, the screens with their own DataTables code, removal of DataTables (TD9-TD14)
---

## Which steps, in one line each?
1. TD9 (M) After TD3. Cut in two: TD9a (#6379) the browser mode in the core with `view_menus.php` and `view_short_links.php`; TD9b (#6416) styles, themes, design files, containers, comments; TD9c (#6463) regions, ads, fields with quick edit; `view_menu_items.php`, a drag-ordered tree whose order is its meaning, a plain table off DataTables. TD9 done. Browser mode (every row printed, client sort and search) and the first printed lists: `view_styles.php`, `view_themes.php`, `view_design_files.php`, `view_regions.php`, `view_menus.php`, `view_menu_items.php`, `view_containers.php`, `view_ads.php`, `view_fields.php`, `view_comments.php`, `view_short_links.php`.

2. TD10 (M) After TD9. Cut in two: TD10a (#6584) product attributes, cart quotes, returns, subscriptions, orders for a contact; TD10b (#6616) arrival dates, ship date adjustments, verified shipping addresses, order reports, duplicate products; the shipping report a plain report table. TD10 done. Commerce lists: `view_cart_quotes.php`, `view_returns.php`, `view_subscriptions.php`, `view_orders_for_contact.php`, `view_product_attributes.php`, `duplicate_products.php`, `view_arrival_dates.php`, `view_ship_date_adjustments.php`, `view_verified_shipping_addresses.php`, `view_shipping_report.php`, `view_order_reports.php`.

3. TD11 (M) After TD9. Done (#6646); notes in `ui/list-table-settings-lists.md`. Settings-type lists: `view_countries.php`, `view_states.php`, `view_zones.php`, `view_tax_zones.php`, `view_currencies.php`, `view_referral_sources.php`, `view_contact_groups.php`, `view_calendars.php`, `view_calendar_event_locations.php`, `includes/templates/view_auto_dialogs.php`.

4. TD12 (M) After TD9. Cut in two: TD12a (#6662) campaigns, campaign history, SMS campaigns, visitor reports, 404 log, backups, Cloudflare DNS, database tables, the gift card redemption history a plain ledger, notes in `ui/list-table-campaign-system-lists.md`; TD12b `view_email_campaign_profiles.php` after #6659 (which holds that file). Campaign, report and system lists: `view_email_campaigns.php`, `view_email_campaign_profiles.php`, `view_email_campaign_history.php`, `view_sms_campaigns.php`, `view_visitor_reports.php`, `view_not_found_log.php`, `backups.php`, `cloudflare.php`, `database_engine.php`, `edit_gift_card.php`.

5. TD13 (M) After TD9. ERP lists without editable cells (`erp_accounts.php`, `erp_invoices.php`, `erp_waybills.php`, `erp_stock.php`, `erp_cash.php`, `erp_aging.php`, `erp_inbox.php`, `erp_account_sync.php`; a ledger whose order is its meaning stays plain) and the screens with their own DataTables code: `includes/templates/view_offers.php` (chip filter as a driver filter), `includes/forms/product_group_form.php`, `includes/panel/explorer.php`, `editor_select_page_or_file.php`, `includes/dashboard/widgets/widget_5.php`.

6. TD14 (M) After every other step. Removal: `assets/lib/DataTables/` and its CSS import, the i18n file, `jquery_datatables_checkbox`, the DataTables setup in `backend.src.js` and its helpers (`pgListServerOptions`, `pgListHeadingCopies`, `chart_checkbox_state_change`), `cdn.datatables.net` in the CSP (`waf.php`); the `ui/` notes written for DataTables corrected or deleted.

## Where are these screens from?
- The 70 files with a `table.chart` on 2026-11-03; a screen found later joins the nearest batch. Each step checks its screens first: a list that turns out to be a plain ordered table is left as it is and named in its PR.
