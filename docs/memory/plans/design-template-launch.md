---
name: design-template-launch
description: Plan for the Phone Launch Site template (pinegrap-launch): three models, specs and compare, the shop, trade-in and support requests; parts in a folder; steps 1-7
---

## What is being built?
- A maker's launch site for its new phone: dark home with the highlights, features, camera, specs per model, compare, about, FAQ; buy page and accessories in the shop; trade-in and support requests followed from the account; the standard pages (blog called Newsroom), the e-mails. Id `pinegrap-launch`, 1.0.0, Bootstrap 5.3.8.
- The phone is fictional: Orbit, in three models (`mini`, `one`, `plus`), four colours, three storage sizes. Every figure lives once in `parts.php` (`$models`, `$spec_groups`, `$phone_prices`) and feeds cards, spec and compare tables and the catalog.

## How does it work without new product behaviour?
- Phones and accessories: products of two catalog groups (with the shop), one product per model and storage size (SKU `ORBIT-<MODEL>-<GB>`), prices from `$phone_prices` written with `$money()`. The colour is chosen on the colours section and named in the product's description: the template catalog makes no variant sets.
- The buy page always stands: with the shop its cards add to the cart, without it they lead to the launch list (group `launch_list`) and contact. Only the accessories list, product, cart, checkout, order and `email_order` carry `requires => ecommerce`.
- A trade-in quote is a record of the Trade-in form (old phone, condition radios, photos in `private`, new model from `?model=`, `_cf.url_param`); status office-use-only, messages on its page's Comments widget, views `submitter_only`. A support request the same way (statuses Open, Waiting for you, Repair in progress, Solved).
- Not built (new behaviour): a launch countdown (a script), variant sets from a template's catalog, pre-order reservations with a later charge, a trade-in price calculator, IMEI checks.
- Turkish trap: check every shared key the template reuses in tr.json before using it (`Process` reads İşlem); a wrong one gets the template's own key.

## Why parts in a folder?
- As Events and Real Estate: steps 1-6 write `includes/design_templates/pinegrap-launch/` (not globbed); step 7's `pinegrap-launch.php` includes them in one scope, each return kept as `$…_part`. Classes `plx-`, field ids `lx-`. Trap: `parts.php` keeps the phone in `$models`; no part may return into that name.

## Which steps are there?
- 1 Data and shared parts: `parts.php` (builders, the phone's data, prices, accessories, press quotes; account bar, header, footer, launch band, page shells, model card, spec table, swatches, form fields, comment thread, staff bar); test `design_template_launch_test.php`.

- 2 Public pages: `pages_public.php` (home, features, camera, specs, compare, about, FAQ).

- 3 Shop: `shop.php` (Phones and Accessories groups, `buy`, `accessories`, product, cart, checkout, order, `email_order`).

- 4 Requests: `mail.php`, `requests.php` (`tradein`, `tradein_view`, `my_tradeins`, `staff_tradeins`, `support`, `support_view`, `my_support`, `staff_support`, `privacy`, `email_reply`, notify e-mails).

- 5 Standard: `standard.php` (contact, inbox, conversation, newsletter as the launch list, Newsroom blog with sample releases, blog post, search, error); test `…_standard_test.php`.

- 6 Account and staff: `account.php`; test `…_account_test.php`.

- 7 Assembly: `pinegrap-launch.php` (order 74, icon `bi-phone`, look `minimal`, palette `lavender`, group `launch_list`, folders, `plx-` styles, two fonts, search map), shared tests' lists, `designer/design-template-launch.md`, plan deleted; PR body names `[NEW] Added the Phone Launch Site design template`.

## Where to start?
- `pinegrap-events/` (catalog from one price table, shop pages beside pages that always stand), `pinegrap-realestate/` (requests), [[design-templates-file-format]], [[design-templates-catalog-and-look]], [[design-template-threads]].
- The next free step is the lowest one not marked done here.
