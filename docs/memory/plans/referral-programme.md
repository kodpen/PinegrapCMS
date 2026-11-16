---
name: referral-programme
description: Plan (owner 2026-11-16, agent channel poll 82): a customer brings a friend by a personal link, the friend gets an offer and the customer store credit, on the affiliate link's rails; steps RF1 to RF5
priority: 6
---

## What is asked?
- The owner's words and the conditions of the set: `plans/more-features.md`. In the study (agent channel message 6433): a customer brings a friend and both gain.

## What is there today, and what is reused?
- The affiliate programme is most of the rails: an approved contact's `affiliate_code`, the link (`pg_affiliate_link()`), the arrival kept for the visit and in the cookie affiliates have, the group offer put in the cart, and the order's `affiliate_code` (`pg_order_submit_affiliate_code()`, the last click wins). My Account has an Affiliate part (`commerce/affiliate-link-offer-code.md`, `commerce/affiliate-code-on-order.md`).
- A commission is made at completion, voided on a cancel and put back on a restore (`pg_order_complete_commission()`, `pg_order_cancel_commissions()`, `pg_order_restore_commissions()`).
- Store credit is a gift card bound to a customer: `pg_credit_issue()`, `pg_credit_void()`, its kinds a fixed list of four (`commerce/store-credit-model.md`). Credit that an order gave is taken back on a cancel and on any refund, with no "pending" state (#6306, `workflow/decisions-made-product-25.md`; `commerce/store-credit-points-take-back.md` is the model).
- The offers' "new customer" condition looks only at an account's orders, so a guest always passes: it cannot stop a repeat.

## What binds it?
- The architect's conditions: `plans/website-second-set-conditions.md` for all eight plans, `plans/website-second-set-conditions-per-plan.md` for this one. They are not repeated here.
- A separate referral link would fight the affiliate link over the visit's one code and the order's one column: so referrals ride the same link, and E2 keeps the two programmes apart.

## What is built?
- The numbered features: `referral-programme-features.md` (E1 to E15). Steps: `referral-programme-steps.md`. Claim as `[plan:referral-programme] <step> — <topic>`.
- In short: a code worked out from the contact, carried by the affiliate link's own rails, and at completion a reward as store credit instead of a commission, with its own checks and take-back.

## What does the owner decide?
- Who can refer (the proposal: a customer with an order that counts) and that the reward is store credit. The table and the new kind of credit (asked with the set's other tables).

## What does the architect decide?
- The table's columns and how the credit's kind is added, in RF1's draft PR. Whether a part refund takes the whole reward (the plan, as #6306 reads) or a share.

## What is left out?
- A reward as points or an offer code; rewards that grow with the number of friends; a reward for a friend's later orders; a public ranking. Each is a later yes.

## Whose look?
- The owner's: "Refer a friend" in My Account (RF3) and the report (RF4). The settings card: the architect's.
