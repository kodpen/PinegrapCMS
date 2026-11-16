---
name: php-error-log-clean-census-4
description: Error log census part 4 (plan php-error-log-clean): the shapes already gone on development, by file with the PR that removed them, most frequent first; nothing here is left for EL5
---

## Which shapes are gone, and by which PR?
- Files with nothing open: `duplicate_products.php` 7,208, `get_file.php` 248, `view_order.php` 70, `includes/designer_ai.php` 50, `print_order.php` 4 (#9657); `includes/workspace/wtable_import.php` 302 (#3461); `shipping.php` 40 (#4581); `includes/fn/ecommerce.php` 28 (#4474); `includes/panel/commerce.php` 27 (#4767); `edit_contact.php` 26 (#942); `backups.php` 24 (#6024); `includes/explorer/handle_recycle.php` 20 (#4247); `edit_product_group.php` 20 (#6591).
- `get_cross_sell_items.php` 19, `edit_pages.php` 14, `allow_or_disallow_new_comments.php` 8, `edit_erp_expense.php` 6, `liveform.class.php` 2, `view_parasut_inbox.php` 1, `includes/forms/product_attribute_form.php` 1, `includes/fn/list_table.php` 16, `includes/erp/profit.php` 2 (#5740): read by hand (part 1).
- `edit_shipping_method.php` 18 (#954); `edit_form_item_view.php` 16, `view_fields.php` 2 (#5725); `includes/fn/widgets.php` 14 (#5905); `includes/panel/designer.php` 14 (#4369, #4774); `get_email_preferences.php` 12 (#4315); `view_order_report.php` 12 (#9728); `get_update_address_book.php` 12 (#4533); `edit_email_campaign.php` 10 (#9215); `edit_calendar_event.php` 8 (#491).
- `delete_submitted_forms.php` 8 (#5647, #6387); `editor_add_file.php` 8 (#9148); `includes/fn/vendor_api.php` 7 (#8881); `clean_up.php` 7 (#5931); `view_sessions.php` 6, `account_security.php` 2 (#6007); `get_my_account_profile.php` 4 (#4832); `duplicate_product_group.php` 4 (#5551); `mail_queue.php` 4 (#5983); `import_products.php` 4 (#5893); `import_users.php` 3 (#6553); `includes/fn/auth.php` 3 (#394); `edit_user.php` 2 (#279).
- `delete_email_campaigns.php` 2 (#5652); `includes/erp/cheques.php` 2 (#5644); `update_calendar_event_exception.php` 2 (#5639); `import_submitted_forms.php` 2 (#5647); `delete_styles.php` 2 (#5852); `device_pairing.php` 2 (#6439); `generate_layout_content.php` 2 (#6533); `includes/fn/email_campaign_create.php` 2 (#6563); `includes/fn/output.php` 1 (#493); `editor_select_page_or_file.php` 2 (file removed); `includes/panel/software.php` 4 (the `mkdir()` line is gone).
- Not faults (part 1): `view_email_campaign_profiles.php` 10, `view_contact_groups.php` 2, `includes/workspace/api_wtables.php` 1, `import_zip.php` 1, `includes/phpexcel/…/pclzip.lib.php` 1, `includes/fn/carrier_labels.php` 1, the ten `includes/design_templates/*.php` 1 each.

## Which gone shapes sit in files that still hold open ones?
- `get_order_preview.php`: the iyzico installment table read from an empty reply, 3,676, and missing checkout page ids, 48 (#4052).
- `view_visitor_report.php`: key "count" 663 (#4369). `edit_form_list_view.php`: "type" and "browse_field_…" keys 59 (#5725).
- `get_order_receipt.php`: "start", "payment_period" 40 (#4257). `get_order_receipt_in_plain_text.php`: offset on null 75 (read by hand), keys 9 (#1421).
- `import_design.php`: `parse_url()` parts 34 (read by hand), keys 32 (#6014), `$http_response_header` 2 (#784); `includes/fn/system_status.php` `$http_response_header` 31 (#784).
- `edit_orders.php` offset on null 42 (read by hand); `edit_design_file.php` key "id" 8 (#5949); `edit_field.php` "quiz_answer" 4 (#3986); `erp_accounts_import.php` 14 (#6566); `includes/fn/calendar.php` 17 (#6794).
- `add_email_campaign.php` 14 (#6502, #5652, #6563); `edit_submitted_form.php` 18 (#2466); `add_theme_file.php` 14 (#5899); `get_page.php` 11 (#4315); `get_page_content.php` 4 (#7977); `get_view_order_screen_content.php` 1 (#5208); `delete_users.php` 2 (#4512); `add_page.php` 2 (#6533); `includes/fn/seo.php` 2 (#279).
