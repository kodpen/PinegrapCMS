---
name: php-error-log-clean-census-2
description: Error log census part 2 (plan php-error-log-clean): files that held open shapes, get_order_preview.php to includes/fn/forms.php, each with the PR that closed it
---

## Which files held open shapes? (most frequent first, part 2 of 3)
- Each line: the file, its lines in the logs (gone shapes included), then the shapes that were open and the PR that closed them. The gone shapes of these files are in part 4.
- A shape gone before its step started was read against the main checkout's `HEAD` at the time of the line (the reflog) and the first commit that changed it.
- `get_order_preview.php` 3,874: done, EL5 #10293.
- `view_visitor_report.php` 895: key "visitors" and foreach() of null 231: gone, #5844 (each group starts with an empty visitor list); EL4b.
- `edit_form_list_view.php` 163: array offset on null 104: gone, #5725 (a view without its row reads every setting empty); EL4b.
- `get_order_receipt.php` 140: done, EL5 #10293.
- `get_order_form.php` 136: done, EL5 #10293.
- `includes/workspace/access.php` 132: key "archived_at": gone, #8446 (the GitHub link card passed the sidebar's short channel row); EL4b.
- `generate_system_theme_css.php` 125: gone, #5899.
- `get_order_receipt_in_plain_text.php` 101: done, EL5 #10293.
- `get_page_content.php` 72: gone, #493, #7977.
- `includes/fn/order_report.php` 62: missing total keys ("count", "subtotal", "tax", "shipping" and six more): gone, #5812 (a report whose first group had an empty name); EL4b.
- `view_orders.php` 55: done, EL5 #10293.
- `get_express_order.php` 48: done, EL5 #10293.
- `edit_orders.php` 48: headers already sent 6: gone, #5743 (the Paraşüt export ends after the zip).
- `edit_design_file.php` 44: gone, #5949.
- `edit_field.php` 42: done, EL5 #10304.
- `includes/api/router.php` 38: gone, #4129.
- `includes/fn/system_status.php` 37: fatal 300 s in the permission walk 6: done, #10113 (issue #10053).
- `includes/fn/designer.php` 32: gone, #5899.
- `erp_accounts_import.php` 24: undefined `$clashes`: gone, #6550.
- `includes/fn/calendar.php` 22: done, EL5 #10304 ("calendars" gone, #6794).
- `add_email_campaign.php` 22: gone, #5652, #6567.
- `edit_submitted_form.php` 20: done, EL5 #10304.
- `add_field.php` 18: done, EL5 #10304.
- `includes/fn/forms.php` 17: done, EL5 #10304.
