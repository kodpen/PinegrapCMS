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
- `generate_system_theme_css.php` 125: gone, #5899.
- `get_order_receipt_in_plain_text.php` 101: done, EL5 #10293.
- `get_page_content.php` 72: gone, #493, #7977.
- `includes/fn/order_report.php` 62: missing total keys ("count", "subtotal", "tax", "shipping" and six more) — EL4.
- `view_orders.php` 55: done, EL5 #10293.
- `get_express_order.php` 48: done, EL5 #10293.
- `edit_orders.php` 48: headers already sent 6 — still.
- `edit_design_file.php` 44: gone, #5949.
- `edit_field.php` 42: done, EL5 #10304.
- `includes/api/router.php` 38: gone, #4129.
- `includes/fn/system_status.php` 37: fatal 300 s in the permission walk 6 — still, #10053.
- `includes/fn/designer.php` 32: gone, #5899.
- `erp_accounts_import.php` 24: undefined `$clashes` — set since.
- `includes/fn/calendar.php` 22: done, EL5 #10304 ("calendars" gone, #6794).
- `add_email_campaign.php` 22: gone, #5652, #6567.
- `edit_submitted_form.php` 20: done, EL5 #10304.
- `add_field.php` 18: done, EL5 #10304.
- `includes/fn/forms.php` 17: done, EL5 #10304.
