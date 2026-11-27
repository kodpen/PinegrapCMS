---
name: marketplace-listing-recycle-bin
description: Why a product in the Recycle Bin is never listed on a marketplace: the queries that leave it out, a line queued before binning closed failed with its reason, Try again after a restore
---

## Are products in the Recycle Bin listed?
- No. A binned product keeps its `products_groups_xref` row (so restore puts it back) but is switched off; sent, it opened a sold-out variant that stayed after the bin was emptied (#916, PR #1238).
- `mp_listing_articles()` (variant count), `mp_listing_items()` and `mp_listing_enqueue()` add `pg_catalog_bin_live_sql('products')`.

## What happens to a line queued before the product was binned?
- It is not sent: `mp_sync_send_listings()` reads it with the bin filter in a `LEFT JOIN` (`mp_listing_split_live()`) and, before the lease, closes it `failed` with "{name} is in the Recycle Bin, so it was not listed." (deleted product: "The product no longer exists.").
- It shows under "What did not go through"; "Try again" after a restore lists it. The article row is untouched; an article whose lines are all binned is not assembled.
- Before PR #1344 it rode with the batch and closed `done` with nothing listed.

Related: docs/memory/commerce/marketplace-listings.md
