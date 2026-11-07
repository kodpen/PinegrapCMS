---
name: design-template-bakery-design
description: Design of plans/design-template-bakery.md: which shipped features the Bakery template reuses for the pickup shop, cake orders, wholesale and classes, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- The daily bake, the celebration cakes (sizes, servings, flavours), the wholesale ranges and the opening hours are page content from `parts.php` data; prices are words written with `$money()` ("From …" for a cake), as the Café menu.
- The pickup shop: bakes, whole cakes and gift boxes are catalog products (shipped by default, so checkout keeps its shipping step). The pickup day is the checkout's requested arrival date (a custom date choice, [[checkout-requested-arrival-date]]) and the place a pickup shipping method naming a stock location ([[stock-locations-pickup]]); both are set in the panel by the site owner, the template only explains them on `how_to_order`. Class places are products with `shippable => false` (the Events tickets, [[design-template-events]]). Only shop pages carry `requires => ecommerce`; shared parts and record pages never link them.
- A custom cake order is a request, not a sale: a record of the Cake Orders form (occasion and cake by `_cf.url_param`, servings, flavour, filling, the words on the cake, a pickup day with `min_date` today, pickup or delivery with the address `required_when` delivery, allergies, an inspiration photo in the `private` folder), an office-only status, the quote and messages on the record page's Comments widget, `submitter_only` view, My Cake Orders, the staff list ([[design-template-realestate]] valuation pattern).
- Wholesale enquiries: the same request pattern (business, kind, ranges by `?range=`, weekly amounts, delivery days, start day with `min_date`), office status, messages, My Enquiries, the staff list.
- Baking classes are a collection (the Hotel's Rooms pattern, [[collection-fields]]): level and kind pick lists, `minutes`, `seats` and price as words, `photo` image kind, office `featured`; lists filter `?level=` ([[collection-lists]]). A place is asked for with a request (`class_booking`, class by `?class=`, wished date with `min_date`), or bought in the shop as a class place product.
- Testimonials as cards, as the Gym.
- Not built (new behaviour): a pickup day chosen per product in the cart (a template catalog cannot give a product its own order form), daily baking limits or sell-outs per day, order cut-off times per product, a cake builder that prices options, standing wholesale orders or trade price lists, class capacity across requests and sales.

## Look and names
- Prefix `pbk-` for classes (`pbk-brand`, `pbk-display`, `pbk-eyebrow`, `pbk-price`, `pbk-lift`, `pbk-quote`, `pbk-hero`), `bky-` for field and section ids (`bk-` is Bookshop's). Two fonts: Playfair Display headings, Nunito Sans body. Look `organic`, palette `coral`, order 99 (95 museum, 97 architecture), icon `bi-cake2`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date, year or month in the sample: pickup days are counted from the order ("two days ahead"), seasons are words.
- Turkish: "fırın" for the bakery, "pastane" for the patisserie side, "ekmek" for bread, "hamur işi" for pastries, "pasta" for a cake, "özel pasta siparişi" for a custom cake order, "teslim alma" for pickup, "toptan" for wholesale, "atölye" for a baking class (never "ders" or "sınıf"); check every shared key in tr.json before reuse.

Related: docs/memory/plans/design-template-bakery.md
