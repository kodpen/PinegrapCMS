---
name: design-template-market
description: Plan for the Marketplace template (pinegrap-market): makers' shops as a Sellers collection beside the store's catalog, seller applications and order help; parts in a folder; steps 1-8
---

## What is being built?
- A makers' marketplace: home, the shop by category and by maker, a page per maker, how it works, sell with us (an application), order help followed from the account, about, FAQ; standard pages (blog "Journal"), e-mails. Id `pinegrap-market`, 1.0.0, Bootstrap 5.3.8.
- The site owner sells everything: one cart, one checkout, one order. Makers are fictional (six shops, five categories, three products each); every figure lives once in `parts.php` (`$categories`, `$sellers`, `$market_products`).

## How does it work without new product behaviour?
- A maker is a record of the Sellers form, a collection ([[collection-fields]]): shop name, maker, city, category, tagline, story, logo and cover (image kinds), `shop_url` (hidden), office `featured`. List filters `?category=` / `?city=`; the maker page is the record view, its reviews on the page's Comments widget.
- Products: the catalog has a group per category and a group per maker (under "Shops"); a product sits in both (`_pg_tpl_catalog()` adds the second group by SKU, `MK-<SELLER>-<N>`). Its description names its maker; `shop_url`'s sample is the maker group's catalog address, the button leaves without the shop (`pg-hide-if-empty`).
- Only shop, product, cart, checkout, order and `email_order` carry `requires => ecommerce`; home, categories and maker pages stand without it.
- A seller application and an order-help request: own forms as the Real Estate valuation ([[design-template-realestate]]): office-only status, messages on the record page's Comments widget, `submitter_only` views, staff lists. An approved maker is added by hand (`seller_new`).
- Not built (new behaviour): seller accounts, seller dashboards, payouts or commissions, per-seller shipping, a product field pointing at a seller record.

## Why parts in a folder?
- As Launch and App Studio: steps 1-7 write `includes/design_templates/pinegrap-market/` (not globbed); step 8's main file includes them in one scope, each return kept as `$…_part`. Classes `pmk-`, field ids `mk-`. Trap: `parts.php` keeps the samples in `$sellers` and `$market_products`; no part may return into those names.

## Which steps are there?
- 1 Done (#6222). Data and shared parts: `parts.php` (data, prices; account bar, header with search, footer, page shells, product, maker and category cards, form fields, comment thread, staff bar); test `design_template_market_test.php`.

- 2 Public pages: `pages_public.php` (home, categories, how it works, sell with us, about, FAQ, gift guide).

- 3 Shop: `shop.php` (category and maker groups, `shop`, product, cart, checkout, order, `email_order`).

- 4 Makers: `sellers.php` (`seller_new` with the Sellers collection and samples, `sellers` with filters, `seller` record page with reviews, the home's list widgets).

- 5 Requests: `mail.php`, `requests.php` (`apply`, `application_view`, `my_applications`, `staff_applications`, `help`, `help_view`, `my_help`, `staff_help`, `privacy`, `email_reply`, notify e-mails).

- 6 Standard: `standard.php` (contact, inbox, conversation, newsletter, Journal blog with maker stories, blog post, search, error); test `…_standard_test.php`.

- 7 Done (#6300). Account and staff: `account.php`; test `…_account_test.php`.

- 8 Assembly: `pinegrap-market.php` (order 77, icon `bi-shop`, look and palette, group `newsletter`, folders, `pmk-` styles, search map), shared tests' lists, `designer/design-template-market.md`, plan deleted; PR body names `[NEW] Added the Marketplace design template`.

## Where to start?
- `pinegrap-launch/` (catalog from one price table, shop pages beside pages that stand), `pinegrap-realestate/` (collection with samples, requests), [[design-templates-file-format]], [[design-templates-catalog-and-look]], [[design-template-threads]].
- The next free step is the lowest one not marked done here.
