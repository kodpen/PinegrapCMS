---
name: design-template-charity
description: Charity template (pinegrap-charity): causes and campaigns with progress, gifts as donation products, pledges and volunteers followed from the account, events, reports, News, pch- look, tests.
---

## What does it ship?
- Home (places `home_campaigns`, `home_events`, `home_posts`), `about`, `our_work` (`#cause-<key>`), `impact`, `transparency` (spending bars, `reports`), `faq`; `donate` (amounts, bank transfer, pledge form), `pledge_view`; shop pages `product`, `cart`, `checkout`, `order`, `email_order`; `campaigns`, `campaign`, `campaign_new`; `volunteer`, `volunteer_view`, `my_volunteering`, `staff_volunteers`, `privacy`, `email_reply`; `events`, `event_view`, `event_new`, `report_new`; standard pages with the blog called News ([[design-templates-standard-pages]]); account and staff pages with `my_pledges`, `staff_pledges`.
- Shared: account_bar, notice, header, footer, staff_bar, donate_band, email_header/footer. Order 81, icon `bi-heart`, look `modern-soft`, palette `emerald` (e-mail green `#047857`). Folders root, public, registration, private and a public `reports`. Contact group `newsletter`. Built in #7468, #7483, #7497, #7531, #7550, #7581, #7631, #7686, #7711.

## Why a main file plus a folder?
- As Market ([[design-template-market]]): `pinegrap-charity.php` includes `parts.php`, `mail.php`, `forms.php`, then `pages_public`, `donate`, `campaigns`, `volunteer`, `events`, `standard`, `account` in one scope, returns kept as `$…_part`; `catalog` comes from `donate.php`.
- Trap: `parts.php` keeps the figures in `$causes`, `$campaigns`, `$gift_amounts`, `$impact`, `$spending`; no part may return into those names.
- Search sections are marked where built (`$searchable()`); the main file has no search map.

## How does it work without new product behaviour?
- A gift is a shop product with the `donation` selection type ([[offer-donation-lines]]): `'donation' => true` in `_pg_tpl_catalog()`, not taxed or shipped; `CH-<CAMPAIGN>` and `CH-GENERAL` in a Donate group. Only shop pages, `email_order` and `address_book` carry `requires => ecommerce`; `donate` always stands.
- A campaign is a Campaigns collection record ([[collection-fields]]): cause pick list (`?cause=` filters), goal and raised as text, `percent` the staff update, office `featured`. The bar is `<progress max="100">` with `value` `{{percent}}` ([[widget-attr-bindings]]); updates are the page's Comments widget, newest first, followed by e-mail.
- Pledges (donate page form) and volunteer offers are form records as Real Estate's valuation: office status, messages as comments, `submitter_only` views, My and staff lists. Events: the Events form dated by `$event_on()` ([[design-template-events]]), a sign-up form opened with `?event=` (`r` is reserved), no seats. Reports: a collection whose file goes to `{{folder:reports}}`.
- Not built: progress computed from orders, recurring gifts, per-donor tax receipts.

## What is the pch- look?
- `pch-brand`, `pch-eyebrow`, `pch-figure` (tabular figures), `pch-progress` (styled bar), `pch-hero`, `pch-band`, `pch-lift`; Nunito headings, Public Sans body. Field ids `ch-`.

## Which tests cover it?
- `design_template_charity_test.php` (data, shared parts, the page key list) and `…_public_`, `…_donate_`, `…_campaigns_`, `…_volunteer_`, `…_events_`, `…_standard_`, `…_account_test.php`; `…_assembly_test.php`: pages in order, widgets once and placed, placeholders, shop-only `requires`, `pch-` rules, six comment widgets (campaign updates not drawn: the shared draw reads flat as oldest first), reply e-mail card, figures kept.
- In the shared lists of the collection-form, account-email, submitter-name, consent-privacy and faded-text tests.
