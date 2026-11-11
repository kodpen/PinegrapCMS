---
name: design-template-bakery
description: Bakery template (pinegrap-bakery): daily bake and cakes as page content, cake orders and wholesale enquiries as requests, baking classes collection, pickup shop, pbk- look, tests.
---

## What does it ship?
- Public pages (`home` with `home_classes`, `google_reviews`, `home_posts`; `bakes`, `cakes`, `how_to_order`, `wholesale`, `visit`, `about`, `faq`); classes (`classes`, `class`, `class_new`); cake orders, class places and wholesale enquiries, each with its view, own and staff lists and e-mails; shop pages; standard pages with the Journal; account and staff pages. The full list: `_test_bk_page_keys()`.
- Shared: account_bar, notice, header, footer, staff_bar, order_band, email_header/footer. Contact group `newsletter` (Bakery Newsletter). Built in #7651–#8192 (eight steps) and the assembly; home and cakes on the Bakery blocks: [[design-template-bakery-home]].

## Why a main file plus a folder?
- `pinegrap-bakery.php` includes `parts.php`, `mail.php`, then `pages_public.php`, `classes.php`, `requests.php`, `wholesale.php`, `shop.php`, `standard.php`, `account.php` in one scope, each return kept as `$…_part`. `mail.php` comes before every page file; `wholesale.php` builds on `requests.php`'s `$bk_…` builders; `classes.php` defines `$bk_filter`, `$bk_page_link`, `$bk_featured_choices`.
- Trap: `parts.php` keeps `$bakes`, `$cakes`, `$baking_classes`, `shop.php` `$catalog`; no part's return is kept under such names.

## Which shipped features does it reuse? (no new schema, module or behaviour)
- The daily bake, cakes (sizes, servings, flavours), wholesale ranges and opening hours are page content from `parts.php`; prices are words from `$money()`.
- A cake order is a request, not a sale (Real Estate's valuation pattern): `?cake=`, `?occasion=`, pickup day `min_date` today, address `required_when` delivery, a photo in the `private` folder, office-only status, messages as comments, `submitter_only` view. Class places (`?class=`) and wholesale enquiries (`?range=`, start `min_date` today) follow the same pattern.
- Baking classes are a collection as the Hotel's Rooms: level and kind pick lists, office `featured` for the home list.
- Pickup shop: bakes, whole cakes (smallest size) and gift boxes are shippable products; the pickup day is the checkout's requested arrival date ([[checkout-requested-arrival-date]]) and the place a pickup shipping method ([[stock-locations-pickup]]), both set by the store. Class places are `shippable => false`. Only shop pages and `address_book` carry `requires => ecommerce`; the shared parts never link the shop, and `how_to_order` links it only while `ECOMMERCE` is on.
- Not built: a pickup day per product, daily limits, cut-off times, a priced cake builder, standing wholesale orders, class capacity.

## What is the pbk- look?
- `pbk-brand`, `pbk-display`, `pbk-eyebrow`, `pbk-hero`, `pbk-lift`, `pbk-price`, `pbk-quote`; field and section ids `bky-` (`bk-` is Bookshop's). Playfair Display headings, Nunito Sans body. Look `organic`, palette `coral` (the e-mails' inline rose), order 139 (137 is Recruitment's), icon `bi-cake2`. No `opacity-*` on text ([[design-template-text-contrast]]); no fixed date: pickup days are counted from the order.
- Turkish: "fırın", "pastane", "ekmek", "hamur işi", "pasta", "özel pasta siparişi", "teslim alma", "toptan", "atölye" for a baking class (never "ders" or "sınıf": `Classes` reads "Sınıflar"); the Journal is "Günce".

## Which tests cover it?
- `design_template_bakery_test.php` and one test per part file load the parts; `…_assembly_test.php` the main file: pages in order, shop links, widgets placed once, folders, search sections, `pbk-` rules, comment threads, Google reviews, samples, the blocks.
- In the shared lists of the collection-form (`blog_new`, `class_new`, `staff_entry`), account-email, submitter-name, consent-privacy (`bky-co-`, `bky-cb-`, `bky-we-consent`), custom-form-rules (`bky-co-date`, `bky-cb-date`, `bky-we-start`) and faded-text tests; url-params covers every template by itself.
