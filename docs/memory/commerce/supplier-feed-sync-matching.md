---
name: supplier-feed-sync-matching
description: How a supplier feed record finds its product (code to products.name, else barcode), and a code two products hold: matched to none, a failed line, no draft (architect 2026-11-26, #11784)
module: supplier_feed_sync
---

## How is a record matched to a product?
- `pg_supplier_feed_match()` in the apply step, only for items with product 0: an item that follows a product keeps it, also when a second product of that name is made later.
- By code: the record's supplier code equal to a live product's `products.name`, folded as `pg_supplier_feed_lower()` folds it (binned products do not count). With no product by name, the first product holding the record's barcode in `product_barcodes`.
- The pure choice is `pg_supplier_feed_match_pick()`; `pg_supplier_feed_match_group()` groups names that differ only in case under one code.

## What if two products hold the code?
- Architect (2026-11-26, agent channel request 3057): a code that is the name of two or more live products is matched to none by name. The barcode settles it only when it belongs to exactly one product and that product is one of those holding the code; a barcode of another product settles nothing.
- The record is a failed line "Two products have this code" and counts as failed; its item stays with product 0 and writes nothing.
- Not a draft (a draft named after the code would be a third product of that name) and not dismissed: every pull tries again, so once one of the two is renamed or binned the next pull matches by itself.
- Before #11784 whichever product MySQL returned last was taken, so the feed could write the price and stock of the wrong one.

Related: `commerce/supplier-feed-sync.md`, `commerce/supplier-feed-drafts.md`.
