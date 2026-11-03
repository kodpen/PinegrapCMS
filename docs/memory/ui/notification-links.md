---
name: notification-links
description: Bell, banner and System Status link only to screens the reader may use: Repair Software for role 0 off hosting; form submissions for the folder's editors
---

## Where does 'Software files were changed' link?
- Repair Software (`software_repair.php`) opens only for an administrator (role 0) on a site that is not hosted. `pg_software_repair_open_to($role, $hosted)` (`includes/fn/ops_alerts.php`) is that test.
- `pg_notification_display($notification, $reader_role = null)` links the `integrity` ops row through `pg_ops_alert_reader_url()`: Repair Software for those, `welcome.php` for everyone else. The panel's bell and banner pass `$user['role']`; a caller with no role (the API's `account.php`) counts as not an administrator.
- The catalogue's own url is `welcome.php` under `pg_hosted()`, so the alert e-mail (`pg_ops_alert_url()`) never points a hosted site at the refusal.
- Before, a designer, a manager or anyone on a hosted site who clicked it got "Access denied" or "managed by the hosting platform".

## Why is the System Status chip gated in the widget?
- `get_system_status_checks()` is cached for every reader, so the File Integrity danger chip keeps `software_repair.php` there. `pg_system_status_chips()` (`includes/fn/health_gauge.php`, drawn by widget 2 and the new dashboard's System Status card) drops the href when `pg_software_repair_open_to($role, pg_hosted())` is false; the chip is then the focusable button that opens its popover.

## Who sees 'A custom form was submitted'?
- `pg_notification_visible()` asks `pg_notification_form_visible()` beside `manage_forms`: edit access to the folder of the form's page (`forms.page_id` → `page.page_folder`, `pg_folder_edit_access()`), as `edit_submitted_form.php` asks. Manager and above pass without a query; the rest are cached per request and person, like `pg_notification_comment_visible()`.
- Every place the ladder runs on partial rows carries `form_id`: the badge columns in `pg_notification_unread_rows()`, `pg_notification_ladder_columns()` and the row `create_notification()` gives `pg_push_enqueue_notification()`. A role-3 user outside the folder no longer counts it, sees it or gets its device banner.
- `tests/notification_links_test.php`.
