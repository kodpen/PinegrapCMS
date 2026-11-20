---
name: referral-checks
description: What each check of a referred friend's order reads (pg_referral_check()): the code's one spelling, self by both e-mails, the friend's other orders; open gaps
module: referral
---

## What does each check read?
- off: `pg_referral_settings()['on']`.
- code: the signature holds (`pg_referral_code_contact()`), and only the spelling `pg_referral_code_make()` writes, in any case: a number with leading zeros is refused (#10627), because the month's room counts rows by the exact code and each spelling had a month of its own.
- affiliate: an approved affiliate holds the code. referrer: no account, an approved affiliate, or no complete/exported order.
- self: the same account, the same contact, or the folded e-mail (lower case, no +tag, Gmail dots). The buyer's billing e-mail is matched against both the referrer's account e-mail and contact e-mail (#10627): a guest order with the contact's e-mail was rewarded before.
- friend: any other complete/exported order by account, else by billing e-mail (`pg_referral_friend_orders_sql()`). Not only lower ids (#10627): of two orders by one link, the one placed first may be completed last and earned a second reward.
- smallest: `pg_referral_order_value()` under `referral_smallest`, or 0. month: rewards of the code this month at the cap.

## What is still open?
- #10629: the friend check misses an account holder's guest orders and e-mail variants (E7 says account, else e-mail).
- #10630: no address match in the self check.
- #10631: a reward is lost when the referrer's lock stays busy past `PG_REFERRAL_LOCK_WAIT`.

## Tests?
- `tests/referral_test.php`.
