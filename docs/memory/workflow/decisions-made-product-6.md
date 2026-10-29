---
name: decisions-made-product-6
description: Decisions list, product part 6 (2026-10-29 night on): subscriptions: later-starting recurring lines, the price-change notice
---

## What happens to a recurring line that starts after the order date? (#3310)
- Decided (architect, 2026-10-29, inside the subscriptions plan): on the driver gateways (iyzico, Stripe; store-run renewals) every recurring line of a paid order gets a `subscriptions` row on the card registered with today's payment: status `active`, `next_charge_at` the line's start date, amount the line price; the renewal job makes the first charge on that date. An order that charges nothing today and has only later-starting recurring lines is refused at checkout on those gateways with a message asking for today as the start date; a card-only Stripe SetupIntent flow (option b) may follow later. No schema. Legacy gateways and their profiles, existing orders and the express-order totals stay unchanged.

## How is a subscription price change noticed and applied? (#3311)
- Decided (architect, 2026-10-29; the plan's approved answer, built): the renewal job finds, at least 14 days before `next_charge_at`, that the product's current price differs from `subscriptions.amount`; it writes a `subscription_events` row (kind `price_notice`, amount the new price, detail the date it takes effect) and sends the `subscription_price_changed` campaign-profile mail; the first renewal on or after that date charges the new amount and updates `subscriptions.amount`. A decrease is noticed and applied the same way, so the mail always matches the charge. No new column; the ENUM appends are in `decisions-made-schema-5.md`.

Related: docs/memory/workflow/decisions-made-product-5.md, docs/memory/workflow/decisions-made.md
