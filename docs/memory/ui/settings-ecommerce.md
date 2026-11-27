---
name: settings-ecommerce
description: Site Settings › Ecommerce as eleven folded rows under Enable Commerce: order, the rules every row keeps, which file holds which row, Gift cards and Rewards as two cards, anchors, what stayed
module: commerce_settings_fold
---

## How is the pane drawn?
- "Enable Commerce" (`ecommerce`, a `pg-switch`) stands at the pane's head, above the rows, with the line "The store is off…" while off; the ERP save needs it there (`ui/settings-features.md`).
- Then folded rows (`pg_settings_fold()`, `ui/settings-fold-rows.md`) in this order: Store, Payment methods, Shipping, Address autocomplete, Stock locations, Gift cards, Rewards, Reference price, Affiliate programme, Referral programme, Product feeds: a shop's set-up first, its programmes after. Order, names, icons, lines and pills: `includes/fn/commerce_settings_fold.php` (`pg_commerce_fold_*()`, pure but for `lang()`). Test: `tests/settings_commerce_fold_test.php`.
- Purpose line (the registry's description): "How the shop sells, takes payment and ships."
- What each row says closed: `ui/settings-ecommerce-row-lines.md`.

## Which file holds which row open?
- Store, Payment methods and the pane's drawing helpers: `ui/settings-ecommerce-store-payments.md`.
- Shipping, Address autocomplete, Stock locations, the "Add a location" reveal: `ui/settings-ecommerce-shipping-stock.md`.
- Gift cards, Rewards, Reference price, the radio rows helper: `ui/settings-ecommerce-giftcards-rewards.md`.
- Affiliate programme, Referral programme, Product feeds, the ticked group list, the New address drawer, the two seller identities: `ui/settings-ecommerce-programmes-feeds.md`.

## What does every row keep?
- No popover, no `div.switch`, no `collapse-switcher`, nothing more than one fold deep: a switch's fields are drawn under it as a group whatever it says. Every switch a `pg-switch`; a plain radio a `pg-seg`, or radio rows with more than four choices or a help line each. Labels in sentence case, one help line a field, at most eight fields before a group label.
- A confirm or a second step opens a drawer (`pg_settings_drawer()`), never a second dialog. No card of a feature that is off is hidden (`ui/settings-card-save-rules.md`).

## What changed for Gift cards and Rewards?
- The old card "Gift Cards & Rewards" is two cards: `pgset-giftcards` (Accept gift cards, Validity, Givex, the bonus on refunds) and `pgset-rewards` (the reward programme, its points and conversion, Grant membership, Send e-mail).
- Old anchors `pgsub-rewards`, `ecommerce_reward_program` and its fields, `credit_points_per_unit`, `credit_points_smallest` open Rewards (`pg_settings_legacy_anchors()`); `pgsub-giftcards` still opens Gift cards. The registry's search words are split between the two. Givex's page of Connections names the "Gift cards" row.

## What stayed?
- Every field's name, id and value, the settings keys, the card ids, `commerce.save.php` and what a save writes, one Save. No setting moved to another pane; a field that moved row got its anchor line in `pg_settings_legacy_anchors()`. Keys stay on Connections; the rows hold behaviour and lead there (`ui/connections-one-place.md`).
- Built in #11868, #11885, #11899, #11919, #11938; no `[NEW]` line, it is the open version's tidy.

Related: docs/memory/ui/settings-fold-rows.md, docs/memory/ui/settings-communication.md, docs/memory/ui/settings-ecommerce-row-lines.md
