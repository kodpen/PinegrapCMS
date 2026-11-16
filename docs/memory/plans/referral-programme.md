---
name: referral-programme
description: Plan (owner 2026-11-16, agent channel poll 82): a customer brings a friend by a personal link, the friend gets an offer and the customer store credit, on the affiliate link's rails; steps RF1 to RF5
priority: 6
---

## What is asked?
- The owner's words and the conditions of the set: `plans/more-features.md`. In the study (agent channel message 6433): a customer brings a friend and both gain.

## What is there today, and what is reused?
- The affiliate programme is most of the rails: an approved contact's `affiliate_code`, the link (`pg_affiliate_link()`), the arrival kept for the visit and in the cookie affiliates have, the group offer put in the cart, and the order's `affiliate_code` (`pg_order_submit_affiliate_code()`, the last click wins). My Account has an Affiliate part (`commerce/affiliate-link-offer-code.md`, `commerce/affiliate-code-on-order.md`).
- A commission is a row under the code as text, with the order, an amount and a state; it is made at completion, voided on a cancel and put back on a restore (`pg_order_complete_commission()`, `pg_order_cancel_commissions()`, `pg_order_restore_commissions()`). Its state is otherwise set by hand.
- Store credit is a gift card bound to a customer: `pg_credit_issue()`, `pg_credit_void()`, its kinds a fixed list of four (`commerce/store-credit-model.md`). Credit that an order gave is taken back on a cancel and on any refund, with no "pending" state (#6306, `workflow/decisions-made-product-25.md`; `commerce/store-credit-points-take-back.md` is the model).
- The offers' "new customer" condition looks only at an account's orders, so a guest always passes: it cannot stop a repeat.

## What binds it?
- The architect's conditions: `plans/website-second-set-conditions.md` for all eight plans, `plans/website-second-set-conditions-per-plan.md` for this one. They are not repeated here.
- A separate referral link would fight the affiliate link over the visit's one code and the order's one column: so referrals ride the same link, and E2 keeps the two programmes apart.

## What is built?
- The numbered features: `referral-programme-features.md` (E1 to E15). Steps: `referral-programme-steps.md`. Claim as `[plan:referral-programme] <step> — <topic>`.
- In short: no table. A code that carries the customer and a signature, on the affiliate link's own rails; at completion a commission row under that code and, in the same step, the reward as store credit.

## What did the owner decide? (2026-11-16)
- Who can refer and what the reward is. Owner (agent channel poll 86, message 6515): "Siparişi olan müşteri getirsin; ödül mağaza kredisi".

## What did the architect decide? (2026-11-16)
- Architect (agent channel message 6553), after the owner asked that what exists be grown instead of a table (poll 84): no table. A referral is a `commissions` row; `gift_cards.kind` gets the value `referral`. No waiting stage: the credit is given at completion (#6306).
- RF1 first counts the readers of `commissions`: one function tells the two programmes apart, and the Commissions screen does not let a referral's row be labelled by hand. The month's room is never read and then written: one statement, or a named lock.
- Architect (message 6554): the code carries the person and a signature and is checked without a table; an approved affiliate's code comes first.
- Still the architect's: whether a part refund takes the whole reward (the plan, as #6306 reads) or a share.

## What is left out?
- A reward as points or an offer code; rewards that grow with the number of friends; a reward for a friend's later orders; a public ranking. Each is a later yes.

## Whose look?
- The owner's: "Refer a friend" in My Account (RF3) and the report (RF4). The settings card: the architect's.
