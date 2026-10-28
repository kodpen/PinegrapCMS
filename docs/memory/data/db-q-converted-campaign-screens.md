---
name: db-q-converted-campaign-screens
description: E-mail campaign screens, submitted form delete, e-mail a friend, affiliate, account, billing, gift card and password reset screens on db_q(): special cases, what stayed raw, guard test.
---

## Which files are converted?
- `delete_submitted_forms.php` 5, `delete_email_campaigns.php` 4, `view_email_campaigns.php` 2, `view_email_campaign_history.php` 2, `view_email_campaign.php` 3, `get_email_a_friend_screen_content.php` 3, `email_a_friend.php` 3, `get_affiliate_sign_up_form_screen_content.php` 2, `get_custom_form_screen_content.php` 2, `edit_form_item_view.php` 3, `preview_form.php` 1, `get_my_account_profile.php` 2, `get_billing_information.php` 1, `reset_password.php` 1 (the user read), `order_preview.php` 2, `order_history_delete_order.php` 2, `remove_gift_card_from_order.php` 1, the registration and membership confirmation screens 1 each, `get_affiliate_welcome_screen_content.php` 1.
- Every value is passed `(string)`: each old SQL quoted it, also ids and session values written unescaped (`$email_campaign['id']`, `$_SESSION['ecommerce']['order_id']`, `$user['id']`), now escaped once. The gift card row's `old_balance` (a float for a Givex card) is `(string)`, the same text PHP wrote before.

## What changed besides the placeholders?
- `view_email_campaign.php` read the campaign by `escape($_GET['id'])` after checking that `$_REQUEST['id']` (cast to int) exists; it now reads the checked `$id`, so an id sent by POST no longer ends on "could not be found" after passing the check.

## What stayed raw?
- Queries without values: the campaign lists (`ORDER BY $order_by` from a fixed column map), the profile count, default country and referral source reads, the registration and membership entrance page reads.
- `view_email_campaign_profiles.php`'s list, completed by `$where`.
- The password UPDATEs of `reset_password.php` and `change_password.php`, completed by `pg_password_changed_sql()`; `change_password.php` has no other raw query. `db_value()` calls are untouched.

## What guards it?
- `tests/db_q_campaign_screens_test.php`: no escaped value in a `db_q()` SQL or a remaining raw query (except those completed by `$where` / `pg_password_changed_sql()`), a minimum `db_q()` count per file, one value per `?`, no quoted `?`, and bind examples.

Related: docs/memory/data/db-q-converted-files.md (conversion rules), docs/memory/data/db-q-converted-campaigns-visitor-report.md
