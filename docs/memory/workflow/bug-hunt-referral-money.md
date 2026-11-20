---
name: bug-hunt-referral-money
description: Bug hunt of the referral programme's rules and money (referral.php, its settings card and mail, the Referrals Report): three faults fixed, three issues closed later, what read clean
---

## Which area was hunted?
- Architect (2026-11-20, agent channel message 10274): the referral programme's rules and money, RF1 #10033, RF2 #10560, RF4 #10571; no hunt had read them.
- Files: `includes/fn/referral.php`, the Referral programme card's save (`commerce.save.php`) and the reward mail, `includes/fn/referral_report.php`, `view_referral_report.php`.

## What was fixed (#10627)?
- A code with zeros before the number (`R0…`) passed its signature; the month's room counts rows by the exact code, so each spelling had its own cap. `pg_referral_code_contact()` reads only the made spelling.
- The friend check counted only orders of a lower id: of two orders by one link, the one placed first and completed last earned a second reward. `pg_referral_friend_orders_sql()` counts any other completed order.
- The self check read only the referrer's account e-mail; a guest order with their contact e-mail was rewarded. Both addresses are matched.

## Which issues were opened?
- #10629 friend check by account and folded e-mail together, #10630 no address match (a known limit), #10631 a busy lock's reward retried: all three closed by #10674 (`commerce/referral-checks.md`, `commerce/referral-rules.md`).

## What read clean?
- Signature (HMAC, `hash_equals`), the lock around check and write, the `done` guard against two rewards for one order, the guarded paid→ineligible take-back, part refunds per message 7257, restore's smallest check and the cap counting taken-back rows.
- Amounts: minor units throughout, the card's save with `round()`, the mail escaped with `h()` and only http(s) links, the Commissions totals excluding referral rows, the report's access and names gate (as the Customers Report).

## Tests?
- `tests/referral_test.php`.
