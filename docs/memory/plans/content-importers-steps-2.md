---
name: content-importers-steps-2
description: Steps IM4-IM7 of plans/content-importers.md: the WooCommerce and Shopify readers, redirects and undo, close-out; claim as [plan:content-importers] IM<n>
---

## Which steps are there?
- Sizes, tests and memory rules as in `content-importers-steps.md`; IM4 and IM5 run beside IM2 and IM3.

- IM4 (M) WooCommerce reader. After IM1.
  - `includes/import/woocommerce.php`: the products export CSV (type simple, variable, variation; parent SKU; attributes as variant sets through `commerce/catalog-variant-set-writer.md`; categories as product groups made on the way; images as media fetches; prices through the existing price cell reader; stock to `inventory_quantity`); the product import's row writer reused, not copied. Tests on the fixture. Memory: `platform/import-woocommerce.md`.

- IM5 (M) Shopify reader. After IM1.
  - `includes/import/shopify.php`: products CSV (handle groups rows into one product, options 1-3 as variant sets, body HTML, images, prices, inventory) and customers CSV (contacts with address, phone, "accepts marketing" as `opt_in` with a consent log line naming the import as the source, tags as a contact group). Tests on the fixture. Memory: `platform/import-shopify.md`.

- IM6 (S) Redirects and undo. After IM2-IM5.
  - Every imported item with an old address (post, page, product, category) gets a redirect through the short-link redirect writer (old path → the new address, 301), listed in the report with a CSV download; Undo removes the redirects with the rest. Memory: `platform/import-framework.md` grows.

- IM7 (S) Close-out. After IM1-IM6.
  - PR body names `[NEW] Added importers for WordPress, WooCommerce and Shopify exports`; decisions to `platform/`; the two plan files and the line in `feature-plans-2026-11.md` deleted; `php tools/memory_index.php`.
