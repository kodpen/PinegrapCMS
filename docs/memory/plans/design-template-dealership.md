---
name: design-template-dealership
description: Plan for the Car Dealership template (pinegrap-dealership): vehicles as a collection with filters, test drive, trade-in and finance requests followed from the account; parts in a folder; steps 1-8
---

## What is being built?
- A car dealer's site: home with featured cars, the stock list with filters, new and used lists, a page per vehicle, service centre, finance, about, visit us, FAQ; test drive, trade-in and finance requests followed from the account; standard pages, blog "Showroom News", e-mails. Id `pinegrap-dealership`, 1.0.0. No shop: a car is sold in the showroom.

## How does it work without new product behaviour?
- A vehicle is a record of the Vehicles form, a collection ([[collection-fields]]): title, make, model, condition, body, fuel, transmission, year, mileage, power, price (number kinds), colour, price label, summary, description, features, `photo_1`-`photo_4` (image kinds); office `status`, `featured`. Lists filter `?make=`, `?body=`, `?fuel=`, `?condition=` ([[collection-lists]]) and sort by price, year or mileage; the vehicle page is the record view.
- Test drive, trade-in and finance requests: own forms as the Real Estate viewing ([[design-template-realestate]]): the car from `?vehicle=` (`_cf.url_param`), office-only status, messages on the record page's Comments widget (`test_drive_messages`, `tradein_messages`, `finance_messages`), `submitter_only` views, staff lists. Trade-in photos go to the `private` folder. A finance request asks for bands (deposit, term, income), never an ID or bank number.
- Not built (new behaviour): a finance calculator or credit check, price-range filters, live stock feeds, online reservation with a payment, saved searches with alerts.

## Why parts in a folder?
- As Real Estate and App Studio: steps 1-7 write `includes/design_templates/pinegrap-dealership/`; step 8's main file includes them in one scope, each return kept as `$…_part`. Classes `pdl-`, field ids `dl-`. Trap: `parts.php` keeps the samples in `$vehicles`; no part may return into that name.
- Turkish: check every shared key in tr.json before reuse; a wrong one gets the template's own key.

## Which steps are there?
- 1 Done (#6214). Data and shared parts: `parts.php` (builders, the dealer, makes, bodies, fuels, sample vehicles, services, finance terms, pick lists; account bar, header, footer, test drive band, vehicle card and facts, page shells, form fields, comment thread, staff bar); test `design_template_dealership_test.php`.

- 2 Public pages: `pages_public.php` (home, service, finance, about, visit, FAQ).

- 3 Done (#6265). Vehicles: `vehicles.php`; home widgets `home_featured`, `home_used`.

- 4 Done (#6264). Test drives and trade-ins: `mail.php`, `requests.php` (requests, privacy, `email_reply`, e-mails); test `…_requests_test.php`.

- 5 Finance: `finance_requests.php` (`finance_apply`, `finance_view`, `my_finance`, `staff_finance`, notify e-mails).

- 6 Standard: `standard.php` (contact, inbox, conversation, newsletter, blog "Showroom News", blog post, search, error); test `…_standard_test.php`.

- 7 Account and staff: `account.php` (sign-in, My Account, staff area, `blog_new` with samples, account e-mails); test `…_account_test.php`.

- 8 Assembly: `pinegrap-dealership.php` (order 75, icon `bi-car-front`, look `corporate`, palette `cobalt`, contact group `newsletter`, folders, `pdl-` User Styles, search map), the shared tests' lists, `designer/design-template-dealership.md`, plan deleted; PR body names `[NEW] Added the Car Dealership design template`.

## Where to start?
- `pinegrap-realestate/` and `pinegrap-appstudio/parts.php` (same layout and request pattern), [[design-templates-file-format]], [[design-template-threads]], [[collection-template-refs]]. Steps 2-7 may run in parallel after step 1. The next free step is the lowest one not marked done here.
