---
name: settings-ecommerce
description: Site Settings › Ecommerce as eleven folded rows under Enable Commerce: order, names, each row's line and pill, Gift cards and Rewards as two cards, anchors, what stayed
module: commerce_settings_fold
---

## How is the pane drawn?
- "Enable Commerce" (`ecommerce`, a `pg-switch`) stands at the pane's head, above the rows, with the line "The store is off…" while off. It left the Store card.
- Then folded rows (`pg_settings_fold()`, `ui/settings-fold-rows.md`) in this order: Store, Payment methods, Shipping, Address autocomplete, Stock locations, Gift cards, Rewards, Reference price, Affiliate programme, Referral programme, Product feeds. Order, names, icons and lines: `includes/fn/commerce_settings_fold.php` (`pg_commerce_fold_order()`, `pg_commerce_fold_face()`, `pg_commerce_fold_<part>()`, pure but for `lang()`). Test: `tests/settings_commerce_fold_test.php`.
- Purpose line (the registry's description): "How the shop sells, takes payment and ships."
- The cards inside are drawn as before (popovers and old switches are still there until the plan's later steps open each row: `plans/settings-ecommerce-tidy.md`).

## What does each row say?
- While the store is off every pill is Off and no row asks for attention; each line still says the part's own state.
- Store: "Next order number 10234 · tax-exempt orders allowed" (the second part only with Tax and Allow tax-exempt on), pill On.
- Payment methods: the methods on by name, in the checkout's order (the card gateway's name or "Cards", PayPal Express Checkout, Pay with iyzico, the redirect drivers, Offline payments). None on: "No payment method is on", amber "None", attention. A card gateway chosen whose keys are not all on Connections: "Connect {gateway} in Connections", amber "Not connected", attention.
- Shipping: "Off" with its switch off; else "One recipient an order" / "Several recipients an order", then the carriers connected (`pg_carrier_api_connected()`, `pg_label_ready()`).
- Address autocomplete: the provider's name (`pg_addr_provider()`), or "Off". Stock locations: "3 locations, 1 off", "1 location", or "Not in use"; no pill.
- Gift cards: "Accepted, valid 365 days", "Accepted, never expire", "Off". Rewards: "On: 100 points to the goal", "Off".
- Reference price: the rule's own words; no pill. Affiliate programme: "On, 10% commission" or "On", "Off". Referral programme: "On, 25 TRY for each friend" or "On", "Off".
- Product feeds: "Built today 03:10 · 412 products" (the day and month too before today, on the site's clock), "Not built yet", "No feed is switched on", or with the store off "The store is off: no feed is built".

## What changed for Gift cards and Rewards?
- The old card "Gift Cards & Rewards" is two cards: `pgset-giftcards` (Accept gift cards, Validity, Givex, the bonus on refunds) and `pgset-rewards` (the reward programme, its points and conversion, Grant membership, Send e-mail).
- Old anchors `pgsub-rewards`, `ecommerce_reward_program` and its fields, `credit_points_per_unit`, `credit_points_smallest` open Rewards (`pg_settings_legacy_anchors()`); `pgsub-giftcards` still opens Gift cards. The registry's search words are split between the two.
- Givex's page of Connections names the "Gift cards" row.

## What stayed?
- Every field's name, id and value, the card ids, `commerce.save.php` and one Save. No setting moved to another pane.

Related: docs/memory/ui/settings-fold-rows.md, docs/memory/ui/settings-communication.md, docs/memory/plans/settings-ecommerce-tidy.md
