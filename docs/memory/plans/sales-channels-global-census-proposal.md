---
name: sales-channels-global-census-proposal
description: Census (2026-11-27) proposal for global sales channels: the order of eBay, Etsy, Amazon and TikTok Shop, what each needs stored beyond today's marketplace tables, the owner's questions
---

## In which order?
- A proposal, not a decision. Facts: the census files beside this one.
- 1 eBay: built (`commerce/marketplace-ebay.md`).
- 2 Etsy: built (`commerce/marketplace-etsy.md`).
- 3 Amazon: the widest reach, but a product type schema per category, GTIN matching, slow order reading, notices only through AWS, the strictest data rules (30 days, WAF), and under model A a private application per seller.
- 4 TikTok Shop: no Turkish market, partner registration in the company's own region with 3 weeks' compliance review, every product audited, images uploaded as files.

## What does each need stored beyond today's tables?
- All four: the OAuth connection in the connected accounts layer (a provider file each, `includes/connect/providers/<key>.php`), named in the account's sealed JSON as Merchant Center does: no column.
- Several countries under one connection: a country or marketplace key on `marketplace_product_map` and the category cache, or one account a country with no column (`sales-channels-global-census-tables.md`).
- A price per country and currency: the markets plan's price list, or a price converted when sent; a currency on `marketplace_price_state` if the repricer is used there.
- Amazon: product type per pairing (`marketplace_category_map` fits).
- TikTok: the remote id of each uploaded image, or every listing uploads its images again (Etsy keeps none, `commerce/marketplace-etsy.md`); the shop cipher an account (sealed JSON fits), the warehouse id, a brand id per product.

## Which questions are the owner's?
- Whose application: each site registers its own at each marketplace (model A, today's rule), or Kodpen registers one and runs a broker (model B, put off 2026-11-21)?
- Which marketplace first, and in which countries?
- Several countries of one marketplace: one account a country, or a country key on listings and the category cache (columns)?
- A price abroad: converted from the base price when sent, or a price list a country from the markets plan?
- Amazon: are orders Amazon ships itself (FBA) imported? n11's warehouse orders are not (`workflow/decisions-made-marketplace.md`).
- Amazon: is an AWS account for order notices acceptable, or is polling enough?
- TikTok Shop: does Kodpen register as a partner (company papers, 3+ weeks), or does each seller make a custom application?
- Is the remote id of an uploaded image stored (a table or a column), or are images uploaded again with each listing?
