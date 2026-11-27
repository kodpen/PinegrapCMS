---
name: settings-ecommerce-row-lines
description: Site Settings › Ecommerce, what each folded row says while closed: its line, its pill and when it asks for attention (Payment methods "None", "Connect … in Connections", Product feeds' last build)
module: commerce_settings_fold
---

## What does a row say while the store is off?
- Every pill is Off and no row asks for attention; each line still says the part's own state. Functions: `pg_commerce_fold_<part>()` in `includes/fn/commerce_settings_fold.php`; test `tests/settings_commerce_fold_test.php`.

## What does each row say closed?
- Store: "Next order number 10234 · tax-exempt orders allowed" (the second part only with Tax and Allow tax-exempt on), pill On.
- Payment methods: the methods on by name, in the checkout's order (the card gateway's name or "Cards", PayPal Express Checkout, Pay with iyzico, the redirect drivers, Offline payments). None on: "No payment method is on", amber "None", attention. A card gateway chosen whose keys are not all on Connections: "Connect {gateway} in Connections", amber "Not connected", attention.
- Shipping: "Off" with its switch off; else "One recipient an order" / "Several recipients an order", then the carriers connected (`pg_carrier_api_connected()`, `pg_label_ready()`).
- Address autocomplete: the provider's name (`pg_addr_provider()`), or "Off". Stock locations: "3 locations, 1 off", "1 location", or "Not in use"; no pill.
- Gift cards: "Accepted, valid 365 days", "Accepted, never expire", "Off". Rewards: "On: 100 points to the goal", "Off".
- Reference price: the rule's own words; no pill. Affiliate programme: "On, 10% commission" or "On", "Off". Referral programme: "On, 25 TRY for each friend" or "On", "Off".
- Product feeds: "Built today 03:10 · 412 products" (the day and month too before today, on the site's clock), "Not built yet", "No feed is switched on", or with the store off "The store is off: no feed is built".

Related: docs/memory/ui/settings-ecommerce.md, docs/memory/ui/settings-fold-rows.md
