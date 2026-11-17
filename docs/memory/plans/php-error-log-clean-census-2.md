---
name: php-error-log-clean-census-2
description: Error log census part 2 (plan php-error-log-clean): the files with shapes still open, most frequent first, from get_order_preview.php to includes/fn/forms.php; verdict words are explained in part 1
---

## Which files still hold open shapes? (most frequent first, part 2 of 3)
- Each line: the file, its lines in the logs (gone shapes included), then only the shapes still open. The gone shapes of these files are in part 4.
- `get_order_preview.php` 3,874: done, EL5 #10293.
- `view_visitor_report.php` 895: key "visitors" and foreach() of null 231 — EL4.
- `edit_form_list_view.php` 163: array offset on null 104 — EL4.
- `get_order_receipt.php` 140: done, EL5 #10293.
- `get_order_form.php` 136: done, EL5 #10293.
- `includes/workspace/access.php` 132: key "archived_at" — EL4.
- `generate_system_theme_css.php` 125: 41 missing keys of a theme's CSS property array ("input", "secondary_buttons", "background_borders_and_spacing", area and font keys) and array offset on null 28 — still.
- `get_order_receipt_in_plain_text.php` 101: done, EL5 #10293.
- `get_page_content.php` 72: array offset on null 65 (`$system_region_properties` with no row) — check.
- `includes/fn/order_report.php` 62: missing total keys ("count", "subtotal", "tax", "shipping" and six more) — EL4.
- `view_orders.php` 55: done, EL5 #10293.
- `get_express_order.php` 48: done, EL5 #10293.
- `edit_orders.php` 48: headers already sent 6 — still.
- `edit_design_file.php` 44: array offset on null 36 (no row check after the files query) — still.
- `edit_field.php` 42: undefined `$form_type_identifier_id`, `$form_type`, `$page_type`, `$output_form_designer_content_heading`, `$form_type_filter` 33 — still. Key "rss_field" — check.
- `includes/api/router.php` 38: `$route['params']` missing and foreach() of null (lines 343, 457, 543) — still.
- `includes/fn/system_status.php` 37: fatal 300 s in the permission walk 6 — still, #10053.
- `includes/fn/designer.php` 32: missing style keys ("shadows_toggle", "rounded_corners_toggle", paddings, corners) — still.
- `erp_accounts_import.php` 24: undefined `$clashes` — set since.
- `includes/fn/calendar.php` 22: undefined `$event_start_date` 5 — still.
- `add_email_campaign.php` 22: keys "page_id", "format", array offset on null 8 — check.
- `edit_submitted_form.php` 20: key "query" 2 — still.
- `add_field.php` 18: key "information" — still.
- `includes/fn/forms.php` 17: key N 10, key "collection" 6 — check.
