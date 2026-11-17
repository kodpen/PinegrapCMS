---
name: account-subscription-credit-open-blocks
description: My Account subscriptions and store credit opened (CB6e2): account.subscriptions with the section's own action forms, account.store_credit and its uses, the points form kept
module: widgets_account, subscriptions_account, store_credit_account
---

## What did CB6e2 open?
- Step CB6e of `plans/designer-closed-blocks-steps.md`, after CB6e1 ([[account-order-open-blocks]], the model: rows, builder, `pg_acct_part()` / `_sdAccountPart()`, starter, canvas sample).
- Tree: `subscriptions` on the new list `account.subscriptions`; `store_credit` on `account.store_credit` (key `account_id`) and `account.store_credit_uses` (key `__credit_id`), the balance as the widget value `__store_credit_balance`.
- Kept the sections' own: a subscription's action forms (pause, resume, cancel asked twice, update card) are the raw row value `__subscription_actions_html` from `pg_subscription_customer_actions_html()`, with the form token; the points conversion form is the new section `store_credit_convert` (`pg_credit_account_convert_html()`, its token field). No form is drawn by the tree.

## Where do the rows come from?
- Subscriptions: `pg_subscription_customer_view_rows($rows, $action_url, $send_to, $token)` (`includes/fn/subscriptions_account.php`): product, amount (minor units / 100), status words, `tone` (success, warning, secondary), the next charge or "Runs until", the price change, the card, the last event, the confirm link, the forms. `pg_subscription_customer_view_html()` prints the section from it, byte for byte development's; `pg_sw_list_account_subscription_rows()` files the list.
- A show condition never reads a markup value (it is refused), so the forms' box shows on `__subscription_has_actions`.
- Store credit: `pg_credit_account_view()` as before; the section is now `pg_credit_account_convert_html()` + `pg_credit_account_cards_html()` inside the balance box; `pg_credit_account_tone()` gives the badge colour; `pg_sw_list_account_credit_rows()` files the cards and their uses.
- `_pg_my_account_extras()` keeps `subscriptions` and `store_credit` (balance_info, cards) and the flags `has_subscriptions`, `has_store_credit_cards`.

## How does a page get the trees?
- "Convert to design elements" on either section's panel; the my account starter places store credit open. Subscriptions are still in no starter. A saved page keeps its sections.
- Opened, inline boxes (the balance, a card's kind and badge, a use's number, date and amount) are separate elements: one space falls between them where the section's spans touched, as CB6e1's order row.

## What does the canvas draw?
- An unopened section: `_sdAccountSubscriptionsHtml()` (an active and a paused subscription with their buttons, `_sdAccountSubscriptionActionsHtml()`), `_sdAccountStoreCreditHtml()` (balance, the points form for 120 points, an open card spent on two orders and a used card); `store_credit_convert` alone draws the form.

## How was it checked?
- `tests/account_closed_blocks_test.php`: development's two builders against the new ones (active, paused, past due with a confirm link, cancelled; open, used and expired cards, with and without the form), the opened parts through their lists against the sections' words, classes, forms and tokens, the rows, the twins, the wiring.
