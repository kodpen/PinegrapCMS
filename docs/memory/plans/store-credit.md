---
name: store-credit
description: Plan (owner 2026-11-02): store credit as customer-bound gift cards: points to credit, refunds to credit, staff-issued credit, the balance applied at checkout; steps SC1-SC7
---

## What is wrong now?
- Reward points only count up: `pg_order_complete_rewards()` adds `products.reward_points` to `user.user_reward_points`, and the one order that reaches the goal (`ECOMMERCE_REWARD_PROGRAM_POINTS`) grants a membership or sends a reward mail. A customer cannot spend points on an order, see them as money, or watch them expire; the gap review found "earn only" where every compared shop offers spendable points.
- A refund goes back to the card or is "recorded another way" (`commerce/order-returns-refund.md`, `payment-refunds.md`); there is no "keep it as credit", which many shops prefer and many customers accept for a small bonus.
- Gift cards exist as codes (`gift_cards`, applied per order in `applied_gift_cards`, balances refreshed at `submit_order.php`), but a card belongs to nobody: a customer must type the code at every checkout, and staff cannot see what a customer holds.

## What is proposed?
- Store credit is a gift card bound to a customer: `gift_cards.user_id`, `kind` (gift as today, points, refund, manual), `reason`, `issued_by`. A customer's balance is the sum of their cards' balances; the account page shows the cards, their expiry and what used them.
- At checkout a signed-in customer with credit sees "Use my store credit (12,50)" ticked; the cards are applied through the gift card path that exists (rows in `applied_gift_cards`, oldest expiry first), so the totals, the receipt, the refund of a cancelled order (`commerce/order-cancel-gift-cards.md`) and reports are the ones gift cards have today; nothing in the express-order totals changes.
- Points become credit by a setting (points per unit of money, a smallest amount) and a button in My Account; a refund of a return or a cancelled order may go to credit with an optional bonus percentage; staff issue or void credit from the customer's card with a reason, written to the audit trail; the job expires cards on their date and mails "credit expiring" ahead.

## Which decisions bind it?
- The reward goal (membership, reward mail) keeps working beside the conversion; a site that sets no points value changes nothing; a card with no `user_id` is a plain gift card as today.
- Money stays in minor units rounded with `round()` (CLAUDE.md fixed rule 7); the conversion rate is read as `pg_parse_amount()` reads amounts; a credit card never pays for a gift card product (the rule `submit_order.php` already keeps).
- Credit is not a payment method: no driver, no charge, no refund to the PSP; a refund to credit is written as a refund in `order_returns.refund_*` with the card id as its reference.

## Which questions are open? (architect; 1 before SC1, 2 before SC5)
1. The four columns on `gift_cards` (`user_id` INT UNSIGNED NOT NULL DEFAULT 0 with a key, `kind` ENUM, `reason` VARCHAR(255), `issued_by` INT UNSIGNED) in the open version's migration; recommended as written, no second table.
2. Mail actions `credit_issued` and `credit_expiring` as `email_campaign_profiles.action` values (ENUM widened by appending), and the expiring notice sent 14 days ahead; recommended as written.

## Where does it start?
- SC1 alone, then SC2, SC3, SC4 and SC5 together. Read `commerce/README.md` key rules, `commerce/order-returns-refund.md`, `order-returns-refund-columns.md`, `order-cancel-gift-cards.md`, `gift-card-balance-edit.md`, `includes/fn/order_completion.php` (`pg_order_complete_rewards()`), `submit_order.php` around `applied_gift_cards`. Steps: `store-credit-steps.md`, `-steps-2.md`.
