---
name: design-template-market
description: Marketplace template (pinegrap-market): makers as a Sellers collection beside the store's catalog, one cart, maker applications and order help followed from the account, pmk- look, tests.
---

## What does it ship?
- Home (places `home_sellers`, `home_posts`), `categories`, `how_it_works`, `sell`, `about`, `faq`, `gift_guide`; the shop (`shop`, `product`, `cart`, `checkout`, `order`, `email_order`); `sellers`, the `seller` record page with its reviews, `seller_new` (staff); `apply`, `application_view`, `my_applications`, `staff_applications`, the same four for `help`, `privacy`, the request e-mails and `email_reply`; the standard pages with the blog called Journal ([[design-templates-standard-pages]]); account and staff pages (`staff`, `blog_new`, `staff_directory`, `staff_entry`), account e-mails.
- Shared: account_bar, notice, header (with the site search), footer, staff_bar, maker_band, email_header/footer. Order 77, icon `bi-shop`, look `organic`, palette `sunset` (the e-mails' inline orange `#c2410c`). Contact group `newsletter` (Marketplace Newsletter). Built in #6222, #6279, #6285, #6286, #6291, #6292, #6300, assembly.

## Why a main file plus a folder?
- As Launch and Dealership: `pinegrap-market.php` includes `parts.php`, `mail.php`, then `pages_public.php`, `shop.php`, `sellers.php`, `requests.php`, `standard.php`, `account.php` in one scope, each return kept as `$…_part`; `catalog` comes from `shop.php`.
- Trap: `parts.php` keeps the samples in `$sellers` and `$market_products`; a part's return under those names would overwrite them.
- `requests.php` owns `privacy` and `email_reply` with its `email_comment` widget.

## How does it work without new product behaviour?
- The site owner sells everything: one cart, one checkout, one order. A maker is a record of the Sellers form, a collection ([[collection-fields]]): city and category pick lists (parts.php's words, so `?category=` / `?city=` filter by them), logo and cover (image kinds), `shop_url` (hidden), office `featured`. Reviews (with stars, threaded one level) are the maker page's Comments widget.
- Catalog: a Marketplace group with a group per category, a Shops group with a group per maker; each product (SKU `MK-<SELLER>-<N>`) sits in both. `shop_url`'s sample is `{{shop_group_url:<maker key>}}`, '' without the shop (#7515), so its button hides.
- Only the shop pages, `email_order` and `address_book` carry `requires => ecommerce`; home, categories, makers and requests stand without it.
- Applications and help requests are records of their own forms as Real Estate's valuation ([[design-template-realestate]]): office-only status, messages on the record page's Comments widget (`application_messages`, `help_messages`), views `submitter_only`, staff lists, photos and receipts in the `private` folder. An approved maker is added by hand (`seller_new`).
- Not built (new behaviour): seller accounts or dashboards, payouts, commissions, per-seller shipping, a product field naming a seller.

## What is the pmk- look?
- `pmk-brand`, `pmk-eyebrow`, `pmk-price` (tabular figures), `pmk-search` (width cap), `pmk-hero` and `pmk-band` (a token tint over the section's own background), `pmk-lift`; two fonts (Fraunces headings, DM Sans body). Field ids `mk-`.
- The unplaced maker card and star builders (and `pmk-stars`) were removed at assembly.

## Which tests cover it?
- `design_template_market_test.php` (data, shared parts) and `…_public_`, `…_shop_`, `…_sellers_`, `…_requests_`, `…_standard_`, `…_account_test.php` load the parts; `…_assembly_test.php` the main file: pages in order, widgets once and placed, placeholders resolve, only shop pages need the shop, search sections, `pmk-` classes equal rules, five comment threads (reviews not drawn: the shared sample is two levels deep), the reply e-mail card, samples kept.
- It is in the shared lists of the collection-form (`blog_new`, `seller_new`, `staff_entry`), account-email, submitter-name, consent-privacy (`mk-ap-consent`, `mk-hp-consent`) and faded-text tests.
