---
name: decisions-made-schema-14
description: Decisions list, schema part 14 (2026-11-09 on): a bundle's own back order choice (bundle_backorder)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the migration shape.

## Which tables and columns were approved?
- Bundle back order choice (owner, 2026-11-09, #8365; bug #7746): `products.bundle_backorder` TINYINT(1) NOT NULL DEFAULT 0, step 11.0.176, filled once from `backorder` for `bundle = 1` rows. The product builder saves a bundle's Backorder tick there; `products.backorder` stays a cache, `bundle_backorder && backorder_allowed`. Why: it keeps the merchant's control and makes the cache reversible. A bundle already stuck at 0 stays 0 until saved once. Chosen over a fully derived switch (the merchant could not refuse back orders) and a hint only (the bug stays).
