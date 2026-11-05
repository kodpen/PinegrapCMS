---
name: membership-expiry-site-date
description: Membership and private-access expiry dates are site-zone calendar days; get_file.php sets the site zone before the active-member flag, the recurring job's "yesterday" is a calendar day
---

## How is a membership tested for expiry?
- `contacts.expiration_date` is a DATE; `0000-00-00` (or empty) is a lifetime membership. A member is active while `expiration_date >= date('Y-m-d')`: the expiry day itself still grants access.
- The same rule is in `initialize_user()` (`includes/fn/auth.php`, defines `USER_MEMBER`), its copy in `get_file.php`, the checks in `get_page.php` and `get_photo_gallery.php`, `includes/fn/audiences.php` and `contact_list.php` (SQL `CURRENT_DATE()`, which follows the site zone because `init.php` sets the session offset).

## Why does get_file.php call initialize_timezone() in initialize_user()?
- `get_file.php` never runs `init.php`, so PHP's zone is the server's until `initialize_timezone()` sets the site's. The active-member flag that `check_view_access()` reads for a membership folder (a comment's or form view's folder before an attachment is served) was worked out with the server's date: a site east of the server let an expired member in after its midnight, a site west of it shut a member out on the evening of the expiry day.
- `check_private_access()` (`includes/authentication.php`) sets the zone the same way before its own date test.

## Which date ends a membership when a recurring profile stops?
- `recurring_payment_job.php` writes the calendar day before today (`strtotime('-1 day', ...)`). `time() - 86400` was still today late on the 25-hour day of an autumn clock change, so the membership was not ended.

## Where is this tested?
- `tests/membership_site_date_test.php`.
