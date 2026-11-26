---
name: settings-ecommerce-giftcards-rewards
description: Site Settings › Ecommerce, the Gift cards, Rewards and Reference price rows opened (EC4): groups, the radio rows helper, the labels and help lines
module: commerce_settings_fold
---

## How are the three rows drawn?
- As Store and Payment methods (`ui/settings-ecommerce-store-payments.md`): no popover, no `div.switch`, no `collapse-switcher`; every field under its switch whatever the switch says. Test: `tests/settings_commerce_giftcards_test.php`.
- `pg_commerce_settings_choice($name, $legend, $options, $help)` draws radio rows as `ui/settings-card-markup.md` (each option's line a `div.form-text.mb-3` after it, the choice's line under the fieldset); a radio's id is `<name>_<value>`. It is the pane's twin of `pg_accounting_settings_choice()`.

## What does each row hold?
- Gift cards: Accept gift cards (`pg-switch`); Validity length with "Leave blank for no expiry."; group headed by its switch "Accept Givex cards" with Givex's connection block (`pg_payment_method_connection_html('givex', …)`), the old "Requires Givex service" alert dropped as the block says the state; group "Store credit" with Bonus on refunds given as store credit.
- Rewards: Reward program (`pg-switch`), Goal, Points per unit of store credit (with its blank line), Smallest conversion; group "When the goal is reached": Grant membership with Membership length, Send e-mail with BCC e-mail address, Subject, Page.
- Reference price: "Price before the discount" as three radio rows (Lowest price of the last 30 days, of the last 10 days, Compare-at price), each with its line, the history line under them.

## What stayed?
- Field names, ids, values, card ids, anchors and `commerce.save.php` unchanged; the radio ids stay `ecommerce_reference_price_rule_<rule>`. The help lines that were "(leave blank …)" are sentence-case new keys.

Related: docs/memory/ui/settings-ecommerce.md, docs/memory/ui/settings-ecommerce-shipping-stock.md
