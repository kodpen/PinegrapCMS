---
name: sales-channels-global-amazon
description: Plan part (architect 2026-11-28): what was settled for Amazon before its steps AZ1 to AZ8: private application, one account a country, offers before new products, no FBA orders, the 30-day rule
---

## Where are the steps?
- `plans/sales-channels-global.md`, AZ1 to AZ8. Facts and the pages they come from: `plans/sales-channels-global-census-amazon.md`. This file holds no step; AZ8 moves what lasts to `commerce/marketplace-amazon.md` and deletes it.

## Whose application connects a seller?
- The site's own, as every connected account (`workflow/decisions-made-panel-14.md`): at Amazon a private application of the seller's own company, authorized by the seller itself in Seller Central. No consent page and no return address: the seller copies the refresh token into Connections › Amazon.
- An application of Kodpen listed in Amazon's store would be the broker the owner put off on 2026-11-21: not built.

## How do several countries fit?
- One marketplace account a country (Amazon's marketplace id in the sealed credentials), as eBay's sites (`commerce/marketplace-ebay.md`). One connection serves every account of its region; the endpoint follows the marketplace.
- Product types and their attributes change by country: the cache goes under `category_key($account)`, `amazon:<marketplace id>`. No table, no column.
- A price is the base price converted to the marketplace's currency when sent.

## What is listed first?
- First, in AZ3: an offer on a product Amazon's catalogue already has, found by the article's barcode. Most goods with a barcode are there, and an offer asks few attributes.
- Then, in AZ4: a product the catalogue lacks, with the required attributes of its product type. The pairing screen's attribute fields hold text, a number and a choice from a list; a required attribute of another shape is said in the listing's error, never guessed.
- Bulk feeds are left out: the queue sends an article a call.

## Are orders Amazon ships itself imported?
- No. Architect (2026-11-28), by the reason of the owner's n11 decision (`workflow/decisions-made-marketplace.md`): the shop's stock counts what the shop can ship itself, and goods sent to a marketplace's warehouse left it when they were sent. The pull asks for seller-shipped orders only, and stock is sent only for what the seller ships.
- The owner was told in the agent channel and may decide otherwise.

## How are orders read?
- By the scheduled pull only, no notice: Amazon's notices need an AWS account, and the plan subscribes none. The Orders API is slow, so the pull keeps to its pace and reads what changed since the last read.
- Buyer and address come only to an application that was given Amazon's roles for them. Without the role an order is imported with no address and its `notice`, as Etsy's (`commerce/etsy-orders.md`); nothing is built apart for it.

## What about Amazon's 30 days?
- Amazon's data policy lets buyer data be kept at most 30 days after delivery. Architect (2026-11-28): a Privacy centre rule "Amazon orders" beside the others (`core/privacy-retention-rules.md`), off by default, run only after its dry run and the shop's yes, through the erasure's own order entries; invoices and ERP records are never reached, as for every rule.
- AZ7 reads the policy's clause again before it builds; if the rule needs a stored value (the day of delivery), it stops and asks the architect.
- Sealed credentials and the firewall the policy asks for are there (`waf/`).

## What is not known?
- Nothing was tried against Amazon. Whether a private application is given the address roles, and how long its review takes, shows only when the owner registers one.
