---
name: referral-programme-features
description: Plan referral-programme, features E1 to E15: settings, who can refer, the signed code on the affiliate link's rails, the checks at completion, the reward as a commission row and credit, its take-back
---

## What is set up?
- (E1) A settings card "Referral programme", off: the friend's offer (an offer that exists), the reward (an amount of store credit and how long it lasts), the smallest order that counts, and how many rewards one customer can earn in a month.
- (E2) Who can refer: a customer with an account and at least one order that counts, who is not an approved affiliate. An affiliate keeps the commission programme; nobody is in both.
- (E3) With the card off nothing changes: affiliate links, commissions and the account pages work as today.

## How does a friend arrive?
- (E4) The customer's code carries their contact and a signature made with a secret of the site: short, not guessable, checked without any table. One function tells a referral code from an affiliate's, and an approved affiliate's code comes first.
- (E5) The link is the affiliate link's own form, so one arrival rule serves both programmes: the code lasts the visit and, with marketing allowed in the cookie notice, the cookie affiliates have; the order's one `affiliate_code` carries it (`pg_order_submit_affiliate_code()`); the last link clicked wins. With tracking off it lasts the visit only.
- (E6) Arriving by a referral link puts the friend's offer in the cart as an affiliate's group offer is put today. Nothing tells the friend who sent them.

## When is a reward given?
- (E7) When the friend's order is completed, in the same step as an affiliate's commission, and only if all hold: the code is a referral code of a customer who can refer; the buyer is not the referrer (account, contact, folded billing e-mail); this friend (account, else e-mail) has no earlier order that counts; the order reaches the smallest value; the referrer's month has room.
- (E8) The referral is a commission row under the code, with the friend's order and the reward as its amount. In the same step the reward is issued as store credit through `pg_credit_issue()` with the kind `referral`, named "Referral" to the customer, and the row is marked paid. No waiting stage.
- (E9) The month's room is taken in one statement or under a named lock, never read and then written: two orders at once cannot pass the cap.
- (E10) The referrer gets a mail through the mail queue: what they earned and their balance.

## When is it taken back?
- (E11) A cancelled order takes the credit back and marks the row ineligible; a restored one gives again; a refund of the order takes it back. What was already spent cannot be taken and is noted, as a points take-back notes it.

## What do people see?
- (E12) My Account gets a part "Refer a friend": the link with Copy and the site's own share links, and the customer's referrals with their state (rewarded, taken back) and credit, the friend shown only as initials.
- (E13) A report "Referrals", read from those rows: by month the referred orders, rewards given and taken back, credit issued and the referred orders' value; the top referrers only for whoever may read contacts.
- (E14) The Commissions list and its API show a referral's row as a referral, and its state cannot be set by hand. `referral.rewarded` is announced once per reward, so a flow or a webhook can follow it.
- (E15) A referral's row names the referrer through its code and the friend through its order: the personal data export and erasure treat it as they treat a commission and an order today, which RF1's census sets down.
