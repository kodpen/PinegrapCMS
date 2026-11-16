---
name: referral-programme-features
description: Plan referral-programme, features E1 to E15: settings, who can refer, the code on the affiliate link's rails, the checks at completion, the reward and its take-back, My Account, the report
---

## What is set up?
- (E1) A settings card "Referral programme", off: the friend's offer (an offer that exists), the reward (an amount of store credit and how long it lasts), the smallest order that counts, and how many rewards one customer can earn in a month.
- (E2) Who can refer: a customer with an account and at least one order that counts, who is not an approved affiliate. An affiliate keeps the commission programme; nobody is in both.
- (E3) With the card off nothing changes: affiliate links, commissions and the account pages work as today.

## How does a friend arrive?
- (E4) The customer's code is worked out from their contact and a secret of the site: short, not guessable, checked by working it out again. No column keeps it.
- (E5) The link is the affiliate link's own form, so one arrival rule serves both programmes: the code lasts the visit and, with marketing allowed in the cookie notice, the cookie affiliates have; the order's one `affiliate_code` carries it (`pg_order_submit_affiliate_code()`); the last link clicked wins.
- (E6) Arriving by a referral link puts the friend's offer in the cart as an affiliate's group offer is put today. Nothing tells the friend who sent them.

## When is a reward given?
- (E7) When the friend's order is completed, in the same step as an affiliate's commission, and only if all hold: the code is a referral code of a customer who can refer; the buyer is not the referrer (account, contact, folded billing e-mail); this friend (account, else e-mail) has no earlier order that counts and no referral row; the order reaches the smallest value; the referrer's month has room.
- (E8) The reward is store credit issued through `pg_credit_issue()` with a kind of its own, named "Referral" to the customer. One referral row ties the referrer, the friend's order and the credit; the month's room is taken with one conditional write, so two orders at once cannot pass the cap.
- (E9) The referrer gets a mail through the mail queue: what they earned and their balance.

## When is it taken back?
- (E10) A cancelled order voids the credit and a restored one gives it back; a refund of the order takes it back. What was already spent cannot be taken and is noted on the row, as a points take-back notes it. No holding time is kept anywhere.

## What do people see?
- (E11) My Account gets a part "Refer a friend": the link with Copy and the site's own share links, and the customer's referrals with their state (ordered, rewarded, taken back) and credit, the friend shown only as initials.
- (E12) A report "Referrals": by month the referred orders, rewards given and taken back, credit issued and the referred orders' value; the top referrers only for whoever may read contacts.
- (E13) `referral.rewarded` is announced once per reward, so a flow or a webhook can follow it.

## What about people's data?
- (E14) A referral row names two people: it is in the personal data export and erasure of both and is kept as long as its order.
- (E15) With tracking off or marketing refused the code lasts the visit only, as an affiliate's does.
