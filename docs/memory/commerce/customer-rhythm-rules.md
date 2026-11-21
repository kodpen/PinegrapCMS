---
name: customer-rhythm-rules
description: Customer rhythm's pure rules (customer_rhythm.php): days per unit, own interval, the chance curve, stages and their moves, due products, and the choices the features left open
module: customer_rhythm
---

## What does the module hold?
- Pure functions on plain arrays, no table or settings read: `pg_customer_rhythm_products()` (H1, H2), `pg_customer_rhythm_customer()` and `pg_customer_rhythm_store_median()` (H3), `pg_customer_rhythm_curve()` / `pg_customer_rhythm_chance()` (H4), `pg_customer_rhythm_expected()` (H5), `pg_customer_rhythm_stage()` / `pg_customer_rhythm_step()` (H6, H7), `pg_customer_rhythm_due_products()` (H8).
- The nightly pass that feeds them: `customer-rhythm-store.md`. The product rule and the curve also come as add-one and finish parts (`pg_customer_rhythm_pairs_add()`, `pg_customer_rhythm_curve_add()`), so the pass holds one customer's lines at a time.
- Orders are judged by the Customers Report's `pg_customer_value_order_counts()` / `pg_customer_value_order_amount()`; the caller keys lines with `pg_customer_value_key()`.

## Which orders are read where?
- A product's rhythm reads marketplace purchases too; a customer's rhythm and the due products skip `type = 'marketplace'`, so a marketplace buyer is never a target.
- Purchases or orders less than a day apart are one visit: their quantities add up and they make no gap. An order with `order_date` 0 is skipped.

## How is a product's rhythm worked out?
- A pair is two purchases of one product by one customer whose first is within 730 days (24 months); its value is days between / units of the first. Median of at least 10 pairs from 5 customers, rounded to 2 places.
- Fewer: the median of its group's pooled pairs (same minimum; with several groups the one with most pairs); `from` says `product` or `group`. A subscription product gets no rhythm and adds no pairs to its groups.

## How is a customer's interval and chance worked out?
- Interval: median of the gaps in days. No gap: the store's median days to a second order (`own` false); a store with none gives no interval and no stage ('').
- Store curve: per band of 0.5 intervals, customers whose longest silence (finished gaps and the open one) reached it, and of those the ones with a finished gap that reached it. A band reached by under 20 customers ends the curve; the curve never rises. Past its end: the fixed curve, capped by the last band.
- Fixed curve (under 200 customers with two orders), by band: 90, 90, 80, 70, 55, 45, 35, 28, 22, 17, 13, 11 %, then 8 % from six intervals on.

## How do stages move?
- Lost and Slipping away only from three intervals on: a low chance earlier stays Late, so a store with few repeat buyers does not mark fresh customers Lost.
- With no order since the stored one, the stored interval is kept and the stage only moves forward (Regular = Won back < Due < Late < Slipping away < Lost). A new order takes the new interval; Won back when the silence before it reached Late at the bounds it ran under.

## When is a product due?
- Due day: last purchase + days per unit × its units; due from 3 days before (the lead). The window has no end here: when a product is named and the brake are in `customer-rhythm-events.md`.
