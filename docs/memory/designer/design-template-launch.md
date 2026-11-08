---
name: design-template-launch
description: Phone Launch Site template (pinegrap-launch): the Orbit in three models, specs and compare from one data file, a buy page that stands without the shop, trade-in and support requests, plx- look, tests.
---

## What does it ship?
- Dark home, `features`, `camera`, `specs`, `compare`, `about`, `faq`; `buy` (always), `accessories`, product, cart, checkout, order, `email_order` (shop only); `tradein`, `tradein_view`, `my_tradeins`, `staff_tradeins`, the same four for `support`, `privacy`; the standard pages with the blog called Newsroom and the newsletter page as the launch list ([[design-templates-standard-pages]]); sign-in and My Account pages, `staff`, `blog_new` (press release), `staff_directory`, `staff_entry`; the e-mail pages.
- Shared: account_bar, notice, header, footer, staff_bar, launch_band, email_header/footer. Order 74, icon `bi-phone`, look `minimal`, palette `lavender` (the e-mails' inline violet `#7c3aed`). Contact group `launch_list`. Built in #6163, #6189, #6202, #6193, #6217, #6213 and the assembly PR.

## Why a main file plus a folder?
- As Real Estate: `pinegrap-launch.php` includes `parts.php`, `mail.php`, then `pages_public.php`, `shop.php`, `requests.php`, `standard.php`, `account.php` in one scope, each return kept as `$…_part`.
- Trap: `parts.php` keeps the phone in `$models` (also `$colours`, `$spec_groups`, `$phone_prices`); a part's return under one of those names would overwrite it (the assembly test checks the three models survive in pages and catalog).

## How does it work without new product behaviour?
- The phone (Orbit `mini`, `one`, `plus`, four colours, three sizes) is written once in `parts.php`; cards, spec and compare tables and the catalog read it. The catalog (`shop.php`) makes the Phones and Accessories groups, one product per model and size (SKU `ORBIT-<MODEL>-<GB>`), prices through `$money()`. The colour is chosen on the colours section, never a variant set.
- Without the shop the buy page's cards lead to the launch list and contact; only the shop pages and `address_book` carry `requires => ecommerce` (assembly test).
- A trade-in or support request is a form record as Real Estate's ([[design-template-realestate]]): model from `?model=` (`_cf.url_param`), photos in `private`, status office-use-only, messages the record page's Comments widget (`tradein_messages`, `support_messages`), views `submitter_only`.
- Not built (new behaviour): a countdown, variant sets from a template's catalog, pre-orders charged later, a trade-in price calculator, IMEI checks.

## How are home and features built?
- On the Product Launch blocks: [[design-template-launch-home]].

## What is the plx- look?
- `plx-brand`, `plx-eyebrow`, `plx-figure` and `plx-price` (tabular figures), `plx-hero` and `plx-glow` (palette light on the dark `data-bs-theme="dark"` sections, `color-mix()` after a plain fallback), `plx-specs`, `plx-lift`, `plx-swatch` / `-lg` / `-<colour>`. The swatches are the only fixed colours: the phone's finishes, the same under any palette. Two fonts (Manrope headings, Inter body). Field ids use `lx-`.
- Turkish trap: `Process` reads İşlem, `Newsroom` Haber Merkezi, `Launch List` Haber Listesi; check a shared key before reusing it.

## Which tests cover it?
- `design_template_launch_test.php` (data, shared parts, public pages), `…_shop_test.php`, `…_requests_test.php`, `…_standard_test.php`, `…_account_test.php` load the parts; `…_assembly_test.php` the main file: offered, the shop's catalog, pages in order, widgets once and all placed, placeholders name pages and folders, only shop pages need the shop, contact search sections, `plx-` classes equal rules, the four comment threads and the reply e-mail card, models kept. It is in the shared lists of the collection-form, account-email, submitter-name, consent-privacy (`lx-ti-consent`, `lx-sr-consent`) and faded-text tests.
