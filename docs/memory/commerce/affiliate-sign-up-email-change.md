---
name: affiliate-sign-up-email-change
description: The affiliate sign-up form also changes the member's account e-mail; which accounts the new address is checked against and what a changed address clears.
---

## Why does the affiliate form touch the account?
- `affiliate_sign_up_form.php` writes the posted Email to the contact and to `user.user_email`, so it is a second e-mail change path beside `email_preferences.php`.

## Which accounts is the new address checked against?
- Every other account's `user_username` and `user_email` (`user_id != ` the member's), as registration and `email_preferences.php` check. Before #PR it checked only other accounts' e-mails, so a member could take an address that was another account's username; `validate_login()` reads that identifier with `LIMIT 1` and could then find either account, locking the username's owner out of sign-in by that name.

## What does a changed address clear?
- `user.token` and `user.token_timestamp`: a pending password reset or set password link mailed to the old address dies, so the old mailbox cannot set the password. An unchanged address leaves the link alone.

## Where is the test?
- `tests/affiliate_sign_up_email_change_test.php` (source checks of the query and the UPDATE).

## What is left as it is?
- A username that equals the old e-mail address does not follow the new one here (the e-mail preferences screen renames it); the username stays unique, so sign-in is unaffected.
