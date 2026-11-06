---
name: design-template-charity
description: Plan for the Charity template (pinegrap-charity): causes, campaigns with progress, gifts as donation products, volunteers, events, news, transparency; parts in a folder; steps 1-9
---

## What is being built?
- A nonprofit's site: home, about, our work by cause, impact, transparency (spending split, reports), FAQ, campaigns with progress, donate, volunteer, events, News (the blog); standard, account and staff pages, e-mails. Id `pinegrap-charity`, 1.0.0, Bootstrap 5.3.8, order 81 (79 left for the gym template), icon `bi-heart`, look `modern-soft`, palette `emerald`, classes `pch-`, field ids `ch-`.
- Every figure lives once in `parts.php` (`$causes`, `$campaigns`, `$gift_amounts`, `$impact`, `$spending`); all fictional, no dates.

## How does it work without new product behaviour?
- A gift is a shop product with the existing selection type `donation` ([[offer-donation-lines]]): the visitor types the amount in the cart. One per campaign plus a general fund (`CH-<CAMPAIGN>`, `CH-GENERAL`) in a Donate group. `_pg_tpl_catalog()` writes no `selection_type` yet: step 3 adds a `'donation' => true` product key (donation, not taxable, not shippable), as `'shippable'` was added for Events.
- A campaign is a Campaigns collection record ([[collection-fields]]): cause pick list, place, summary, story, cover (image), goal and raised (text), `percent` (number, staff update it), `donate_url` (hidden), office `featured`. The bar is `<progress max="100">` with `value` `{{percent}}` ([[widget-attr-bindings]]; `value` is not refused).
- Only product, cart, checkout, order and `email_order` carry `requires => ecommerce`. `donate` always stands: amounts, a bank transfer box and a pledge form (Pledges record, office status).
- Volunteers: a form record as Real Estate's valuation (office status, messages as comments, `submitter_only` view, staff list). Events: the Events form, dated by `$event_on()` ([[design-template-events]]), a short sign-up form, no seats. Reports: a collection with a file in a public `reports` folder.
- Not built: progress computed from orders, recurring gifts by default, per-donor tax receipts. A step that needs a schema, module or product behaviour asks the architect first.

## Why parts in a folder?
- As Market: steps 1-8 write `includes/design_templates/pinegrap-charity/`; step 9's main file includes them in one scope, returns kept as `$…_part`. Trap: no part may return into `$causes` or `$campaigns`.

## Which steps are there?
- 1 Done (#7468). Data and shared parts: `parts.php`; test `design_template_charity_test.php`.

- 2 Done (#7483). Public pages: `pages_public.php`; places `home_campaigns`, `home_events`, `home_posts`, `reports`.

- 3 Donate: `_pg_tpl_catalog()` `'donation'` key and test; `donate.php` (Donate group, `donate`, pledge form, `pledge_view`, product, cart, checkout, order, `email_order`, `cart_link`).

- 4 Campaigns: `campaigns.php` (collection with samples, `campaign_new`, `campaigns` with cause filter, `campaign` page with progress and updates as comments, `home_campaigns`).

- 5 Volunteers: `mail.php`, `volunteer.php` (`volunteer`, `volunteer_view`, `my_volunteering`, `staff_volunteers`, `privacy`, `email_reply`, notify e-mails).

- 6 Events and reports: `events.php` (`events`, `event_view`, `event_new`, `home_events`; Reports collection, `report_new`).

- 7 Standard: `standard.php` (contact, inbox, conversation, newsletter, News, blog post, search, error); test `…_standard_test.php`.

- 8 Account and staff: `account.php`; test `…_account_test.php`.

- 9 Assembly: `pinegrap-charity.php` (folders incl. `reports`, `pch-` styles, search map, group `newsletter`), shared tests' lists, `designer/design-template-charity.md`, plan deleted; PR body names `[NEW] Added the Charity design template`.

## Where to start?
- `pinegrap-market/`, `pinegrap-events/`, [[design-templates-file-format]], [[design-templates-catalog-and-look]], [[design-template-threads]]. The next free step is the lowest one not marked done.
