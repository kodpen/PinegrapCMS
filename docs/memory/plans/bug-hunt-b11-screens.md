---
name: bug-hunt-b11-screens
description: Bug hunt, second round: the root panel screens redesigned to the wide layout under B11 of plan bootstrap-6-panel (Oct-Nov 2026), each group with its issues or clean
---

## What is hunted?
- The screens a B11 PR redesigned since 2026-10-25 (`git log --since=2026-10-25 --grep=B11 origin/development`), read against the redesign diff: lost CSRF or permission checks, unescaped output, fields no longer saved or shown, broken links or JavaScript, PHP 7.1-8.5 breakage.
- One `bug` issue per fault (`workflow/test-coordinator.md`); the hunt fixes nothing.

## Which groups, in which state?
- A, ERP: add_erp_invoice (invoice an order), add_erp_manual_invoice, edit_erp_invoice_draft, add_erp_quote, add_erp_return, add_erp_receipt, erp_receipt, add_erp_till, add_erp_transfer, add_erp_purchase_order, add_erp_waybill, edit_erp_waybill, edit_erp_expense, erp_expense_categories, erp_cheque, erp_cheques, erp_bank_statement, erp_bank_statements, erp_reconciliation, erp_stock_count, erp_stock_counts, erp_stock_minimums, erp_settings, erp_account_prices, erp_accounts_import, erp_export: clean (token checks, `validate_erp_access` areas, field names, money columns unchanged).
- B, settings and tools: api_settings, cloudflare, marketplace_settings, smtp_settings, connections, page_experiments, send_email_campaign, import_email_campaign_profiles, approve_affiliate, view_visitor, view_visitor_report, view_order_report, view_system_style_source, clean_up, find_and_replace, mass_edit, delete_key_codes, barcode_increase_inventory, barcode_decrease_inventory: clean.
- C, imports and files: import, import_design, import_zip, import_products, import_contacts, import_users, import_submitted_forms, import_shipments, import_key_codes, add_file, edit_file, create_file, duplicate_folder, editor_edit_file, edit_theme_css, edit_javascript, add_theme_file, edit_theme_file, add_design_file, edit_design_file, edit_page_layout: done #7519 (import_contacts Summary count is site-wide); the rest clean.
- D, content create/edit: add/edit user, ad, ad_region, field, custom_style, container, submitted_form, common_region, designer_region, dynamic_region, login_region, menu, menu_item, auto_dialog, edit_comment, edit_form_list_view, edit_form_item_view, edit_featured_and_new_items, duplicate_product_group: clean.
- E, commerce and campaigns create/edit: add/edit email_campaign, sms_campaign, product_attribute, currency, country, state, tax_zone, arrival_date, ship_date_adjustment, verified_shipping_address, calendar, calendar_event_location, contact_group, referral_source, key_code, edit_commission, edit_recurring_commission_profile: clean.
- F, order screen: view_order.php, read against its four B11 parts (#7465 layout, #7481 shipping card fields, #7495 card rows, #7523 order lines): clean (form token and delete button still inside the form, every card, row and order-line fact still printed and escaped as before, shipped quantity, date and tracking fields keep their names, ids and the minus/plus script).

## What was seen but is not a fault?
- A "Last Modified" row (tax zone, state, country, arrival date, field) shows an epoch-relative time for a legacy row whose timestamp is 0, where referral source shows a dash: display only.
- An order-line fact (custom product field, gift card recipient) with an empty value is left out, where the old box showed it blank: by design (`pg_order_view_line_fact()`).
- `view_order_report.php`'s save bar puts `position-sticky` on the `<nav>` inside a plain column, so it sits at the end of the form instead of sticking: cosmetic, saving works.
- `edit_submitted_form.php` prints `$reference_code` without `h()`, as before the redesign; the code is generated, never typed.
