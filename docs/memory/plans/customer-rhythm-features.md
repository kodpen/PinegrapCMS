---
name: customer-rhythm-features
description: Plan customer-rhythm, features H1 to H16: product rhythm, customer rhythm and chance, stages, reorder due, the link, events, group rule, measuring, report, card, settings, API
---

## How is a product's rhythm learnt?
- (H1) Over 24 months, each time the same customer buys a product again: the days between the two orders divided by the units of the first (days per unit). Its median, from at least 10 pairs of 5 customers; fewer: its product group's median; none: no reminder for it. Kept in one `config_text` summary rebuilt whole.
- (H2) A product sold as a subscription (`subscriptions`) is left out: it runs on its own clock.

## How is a customer's rhythm learnt?
- (H3) Per customer (Customers Report key and counting rule; site and counter orders): orders, first, last, own interval (median of the gaps). One order: the store's median days to a second order.
- (H4) Chance still a customer: among the store's customers whose silence once reached the same multiple of their own interval (bands of 0.5), the share who ordered again; a fixed curve under 200 customers with two orders.
- (H5) Expected 12-month spend: chance × 365 / own interval × own average order, at most twice their best 12 months; shown with its "As of".

## Which stages are there?
- (H6) With own interval i and days since the last order d: Regular (d < i), Due (i ≤ d < 2i), Late (2i ≤ d < 3i), Slipping away (d ≥ 3i, chance 10% or more), Lost (chance under 10%), Won back (the first order after Late, Slipping away or Lost, until d reaches i).
- (H7) The bounds are fixed at each order; between orders a stage only moves forward; one `customer.rhythm_stage_changed` per move, none at a rebuild that only changes the store curve.

## When is a reorder due?
- (H8) Per customer's last purchase of a product with a rhythm: due day = purchase day + days per unit × units; the window opens 3 days before. One `customer.reorder_due` per customer per 7 days naming every product in its window, its last quantity and the link; not for a product bought again since, off sale or out of stock.
- (H9) The reorder link, signed for 30 days, only fills a cart with those products and quantities at today's price and stock; it shows no order, name or address and signs no one in. Expired: `order_history_reorder.php` after sign-in.

## How is it told?
- (H10) Both events through `pg_announce()`: webhook catalogue, payload fixture, API reference block, flow start box with stage filters; right `contacts`, only on a site with commerce on. Payload: contact, stage and the one before, own interval, days since the last order, chance, expected value, last order number; reorder: products (id, name, last quantity, due day) and the link. No address.
- (H11) Contact group rule "Rhythm stage is" for campaigns to a group.
- (H12) Comparison share (setting, off by default): one due customer in ten is held back, no event; the report shows both groups' return rate.

## Is it measured?
- (H13) An order by the same customer within 14 days of a reorder event counts as "came back after a reminder": per month, reminders, came back, value; against the held-back group when H12 is on.

## Where is it seen?
- (H14) Customers Report, Rhythm part: customers and expected value per stage; the list filtered by stage (names with the contacts right) and its CSV; products with their rhythm.
- (H15) Contact card (`edit_contact.php`): stage, own interval, next due day, chance, expected 12 months, products due. Settings in `config_text` rows: on or off, the 7 days, the 3 days' lead, H12.
- (H16) `GET /reports/customer-rhythm` (`orders:read`; names with `customers:read`): stage counts, the list paged by stage, `?contact_id=` for one; an MCP tool by the route rule. The nightly pass in bounded runs, timed on a 200k-order copy in RY1b.
