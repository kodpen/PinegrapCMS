---
name: referral-programme
description: Referral programme start file: a customer brings a friend by a personal link on the affiliate link's rails, the friend gets an offer and the customer store credit; its parts, decisions, PRs and files
module: referral
---

## What is it?
- A customer with an account and an order that counts shares a personal link; a friend's first order that counts earns the customer store credit (kind `referral`) at completion, and the friend gets the offer set on the card. Off by default; off changes nothing.
- No table: the code is signed (`referral-rules.md`), a referral is a `commissions` row, the reward a store credit card. Built in 2026.11.0: RF1 #10033, RF2 #10560, RF3 #10650, RF4 #10571; fixes #10627 and #10674.

## Which file holds what?
- The code, arrival, checks, reward, take-back, cap: `referral-rules.md` (RF1).
- What each check of a friend's order reads, the household limit: `referral-checks.md`.
- A reward whose referrer's lock was busy, tried again by the general job: `referral-busy-retry.md` (#10674).
- Every reader of `commissions` (Commissions screen, Edit Commission, `GET /commissions` `kind`, export and erasure): `referral-commission-readers.md`.
- Settings › Ecommerce › "Referral programme" and the referrer's mail: `referral-settings-card.md` (RF2).
- My Account's "Refer a friend": `referral-account-part.md` (RF3).
- "Referrals Report" and the `referral.rewarded` event: `referral-report-and-event.md` (RF4).
- Code: `includes/fn/referral.php`, `widgets_referral.php`, `referral_report.php`; `view_referral_report.php`; tests `tests/referral_test.php`, `tests/referral_report_test.php`.

## Who is a referrer, who is a friend?
- Referrer: an account, at least one complete or exported order, not an approved affiliate. An affiliate keeps the commission programme; nobody is in both, and an approved affiliate's code wins on an order.
- Friend: no other complete or exported order with the buyer's account, or with a billing e-mail that folds to one of the buyer's e-mails (#10674, `decisions-made-product-82.md`). The buyer is not the referrer by account, contact or folded e-mail.

## What did the owner decide? (2026-11-16)
- Owner (2026-11-16, agent channel poll 86, message 6515): "Siparişi olan müşteri getirsin; ödül mağaza kredisi" — a customer with an order refers; the reward is store credit.
- Owner (2026-11-16, poll 84): grow what exists instead of adding a table.

## What did the architect decide?
- Architect (2026-11-16, agent channel message 6553): no table; a referral is a `commissions` row; `gift_cards.kind` gets `referral`; no waiting stage, the credit is given at completion and taken back on a cancel or refund (#6306).
- Architect (2026-11-16, message 6554): the code carries the person and a signature, checked without a table; one link with affiliates, since a second link would fight over the visit's one code and the order's one column.
- Architect (2026-11-16, message 7257): part refunds, the Commissions totals, `kind`, Edit Commission read-only: `decisions-made-product-68.md`.
- Architect (2026-11-20, message 10330): the friend check, no address match, busy retry: `decisions-made-product-82.md`.

## What is left out?
- A reward as points or an offer code, rewards that grow with the number of friends, a reward for a friend's later orders, a public ranking: each a later yes.
- A restore gives the whole reward again even when part was spent before the cancel. No address match in the self check (a known limit).

## What was not run?
- No browser or database run for the account part's Copy button and share links, or the report on a live site.
