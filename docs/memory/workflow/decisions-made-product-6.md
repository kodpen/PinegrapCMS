---
name: decisions-made-product-6
description: Decisions list, product part 6 (2026-10-29 night on): subscriptions (later-starting lines, price notice, operator screen), AI assistant and add-ons wait, printed order's shipping card
---

## What happens to a recurring line that starts after the order date? (#3310)
- Decided (architect, 2026-10-29, inside the subscriptions plan): on the driver gateways (iyzico, Stripe; store-run renewals) every recurring line of a paid order gets a `subscriptions` row on the card registered with today's payment: status `active`, `next_charge_at` the line's start date, amount the line price; the renewal job makes the first charge on that date. An order that charges nothing today and has only later-starting recurring lines is refused at checkout on those gateways with a message asking for today as the start date; a card-only Stripe SetupIntent flow (option b) may follow later. No schema. Legacy gateways and their profiles, existing orders and the express-order totals stay unchanged.

## How is a subscription price change noticed and applied? (#3311)
- Decided (architect, 2026-10-29; the plan's approved answer, built): the renewal job finds, at least 14 days before `next_charge_at`, that the product's current price differs from `subscriptions.amount`; it writes a `subscription_events` row (kind `price_notice`, amount the new price, detail the date it takes effect) and sends the `subscription_price_changed` campaign-profile mail; the first renewal on or after that date charges the new amount and updates `subscriptions.amount`. A decrease is noticed and applied the same way, so the mail always matches the charge. No new column; the ENUM appends are in `decisions-made-schema-5.md`.

## Does the panel get an operator Subscriptions screen? (#3343)
- Decided (architect, 2026-10-30, inside the subscriptions plan): yes, `view_subscriptions.php` under orders, gated like `view_orders.php`: a list (customer, product, amount, period, status, next charge, card last 4; status and next-charge filters) and a row page (events, renewal orders). Operator actions pause, resume and cancel at period end, each a `subscription_events` row naming the operator, with the existing campaign mails; no "cancel now", no refunds, no price or period edits (refunds stay on the renewal order's screen). No schema. After slice 5.

## Does the visitor AI assistant start in 2026.11.0? (#3313)
- Decided (owner, 2026-10-30): no; the plan stays a weak plan for now and is evaluated later, the roadmap's watch-list line stays. The architect's design for the day it starts is in `plans/visitor-ai-assistant.md`.

## Does the add-on system start in 2026.11.0? (#3344)
- Decided (owner, 2026-10-30): no, after 2026.11.0 ships; the plan stays on the watch list and new PSP, carrier and marketplace drivers keep landing in the core. The architect's notes for the start are in `plans/addon-hook-system.md`.

## Does the printed order show the recipient's shipping card? (#3346)
- Decided (owner, 2026-10-30): yes: `print_order.php` prints its existing `$output_shipping_details` card for each real recipient above that recipient's items, empty rows hidden, in the print view. Until then the printout showed no recipient name or address.

Related: docs/memory/workflow/decisions-made-product-5.md, docs/memory/workflow/decisions-made.md
