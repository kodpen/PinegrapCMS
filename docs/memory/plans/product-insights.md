---
name: product-insights
description: Plan (owner 2026-11-22): Edit Product gets a head and a Performance card (units sold, revenue, views, conversion, returns, left carts, favourites); the rest of the kept screen stays
priority: 6
---

## What is asked?
- Owner (2026-11-22, in the architect's window): "bunu ve ürün sayfasını ve tüm siparişler ekranıyla birlikte güncelleyelim. ürün düzenleme sayfasına ürün hakkında bilgiler de yer verelim. mesela ürün kaç tane satılmış kaç kere sepette bırakılmış gibi analitikler."
- The screen is Edit Product (`edit_product.php`, drawn by `product_builder.php`).

## Where is the drawing?
- `commerce/commerce-screens-drawing/product.html` with `product-dark.jpg` and `product-light.jpg`: board P1 the screen's top at 1440 px, P2 the card loading, with no sale, on a phone. The drawing wins over these words, but for its Turkish, which is a sample.

## Is it accepted?
- Yes. Owner (2026-11-22), shown the drawing: "onay" (`workflow/decisions-made-panel-15.md`). The Product Builder is one of his kept screens (`ui/look-kept-screens.md`): its own file changes for the head and the card only.

## What changes on the screen? (board P1)
- A head under the breadcrumb: the cover picture, the Short Description as the title, pills (Enabled or Disabled; with inventory on, "In stock" with the quantity, "Out of stock" or "Backorder"), a grey line (Product ID / SKU with a copy button, the price, the first parent group, the last change). Right: Duplicate, Product Form, and "View on site" (the product's page in a new tab).
- "Performance" as the first entry of the section list and the first card of the form column, on Edit Product only.
- Nothing else: every section, the Preview column and the save bar stay as they are.

## How is the card put together? (marks 2 and 3)
- Title "Performance" and a segmented control: 30 days, 12 months, All (the person's choice, kept in the browser).
- Eight figures, four across (two on a phone): Units sold, Revenue, Orders, Views, Conversion, Returns, Margin, Last sale; a change line under the first four.
- A bar chart of units sold: by day, month or year as the period is.
- "Carts and interest": Left in carts with "View carts", In open carts, Saved for later, Favourites, Waiting for stock, Rating.
- A foot: "Orders of this product" (All Orders with the Product Name filter).
- What each figure counts: `product-insights-figures.md`.

## How does it behave?
- Read only: it writes nothing and is no part of Save.
- It loads after the page has opened, by a panel action, with skeleton rows meanwhile; a failed read shows one line and "Try again".
- With no sale in the period it says "No sales in this period" and still shows views and interest.
- Who sees it: whoever may open Edit Product; Margin only where the order's Profit card would show (`erp/erp-order-profit-screens.md`).

## What is left out?
- A variant set's total over its products, a comparison of products, "bought together", an export of the figures, the same card on a product group.

## Which steps?
- PI1 (M), done in #11094: `includes/fn/product_insights.php` (named in its memory file's `module:`), the readers and their pure parts with tests, the panel action, the ten-minute keep.
- PI2 (M) after PI1: the head, the section entry and the card in `product_builder.php` and its script; patterns "Record head" (shared with View Order's OS1, whichever lands first adds it) and "Figure grid" on Design examples.
- PI3 (S): `commerce/product-insights.md` written from what was built, `ui/look-kept-screens.md` corrected, these plan files deleted.
- Checked as `order-screen-redraw-steps.md` says; PI1's PR shows `EXPLAIN` of each read for a product with many lines. A read that needs an index stops and asks the architect.

Related: docs/memory/plans/product-insights-figures.md, docs/memory/plans/orders-list-redraw.md, docs/memory/plans/order-screen-redraw.md
