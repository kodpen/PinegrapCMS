---
name: referral-checks
description: What each check of a referred friend's order reads (pg_referral_check()): the code's one spelling, self by both e-mails, the friend's other orders by account and folded e-mail; the household limit
module: referral
---

## What does each check read?
- off: `pg_referral_settings()['on']`.
- code: the signature holds (`pg_referral_code_contact()`), and only the spelling `pg_referral_code_make()` writes, in any case: a number with leading zeros is refused (#10627), because the month's room counts rows by the exact code and each spelling had a month of its own.
- affiliate: an approved affiliate holds the code. referrer: no account, an approved affiliate, or no complete/exported order.
- self: the same account, the same contact, or the folded e-mail (lower case, no +tag, Gmail dots). The buyer's billing e-mail is matched against both the referrer's account e-mail and contact e-mail (#10627): a guest order with the contact's e-mail was rewarded before.
- friend: any other complete/exported order with the buyer's account (`pg_order_rights_user_id()`) or whose billing e-mail, folded, equals one of the buyer's e-mails: the order's billing e-mail and the account's e-mail (`pg_referral_buyer_emails()`) (#10674, #10629). Before, it read the account, else the e-mail, unfolded: a guest order placed before the account was opened, and each `+tag` or Gmail-dots spelling, counted as a first order.
- How: SQL only narrows (`pg_referral_friend_orders_sql()`, a LIKE per e-mail from `pg_referral_email_like()`); `pg_referral_friend_orders_count()` compares in PHP with `pg_referral_fold_email()`, the one fold rule. No second fold in SQL. Not only lower ids (#10627): of two orders by one link, the one placed first may be completed last.
- smallest: `pg_referral_order_value()` under `referral_smallest`, or 0. month: rewards of the code this month at the cap.

## What is a known limit?
- No address match in the self check (#10630): a referrer can order by their own link as a guest, with a new e-mail and their own address, and be rewarded. People in one household rightly refer each other, so an address rule would refuse real friends.
- The harm is bounded by the month's cap (`referral_month_cap`) and the smallest order (`referral_smallest`), and the Referrals Report shows the top referrers (`referral-report-and-event.md`).

## Tests?
- `tests/referral_test.php`.
