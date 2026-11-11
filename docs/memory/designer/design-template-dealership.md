---
name: design-template-dealership
description: Car Dealership template (pinegrap-dealership): vehicles as a collection with filters, test drive, trade-in and finance requests followed from the account, pdl- look, tests.
---

## What does it ship?
- Home (2.0.0, #8640: a showroom on the palette blocks, [[designer-blocks-dealership]]; places `home_featured`, `home_used`, `google_reviews`, `home_posts`), `service`, `finance`, `about`, `visit`, `faq`; `vehicles`, `new_cars`, `used_cars`, the `vehicle` record page, `vehicle_new` (staff); `test_drive`, `test_drive_view`, `my_test_drives`, `staff_test_drives`, the same four for `tradein`, `privacy`; `finance_apply`, `finance_view`, `my_finance`, `staff_finance`; the standard pages with the blog called Showroom News ([[design-templates-standard-pages]]); sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`; the e-mail pages. No shop: a car is sold in the showroom (only `address_book` carries `requires => ecommerce`).
- Shared: account_bar, notice, header, footer, staff_bar, test_drive_band, email_header/footer. Order 75, icon `bi-car-front`, look `corporate`, palette `cobalt` (the e-mails' inline blue `#0047ab`). Contact group `newsletter` (Showroom Newsletter). Built in #6214–#6275 and the assembly PR.

## Why a main file plus a folder?
- As Real Estate and App Studio: `pinegrap-dealership.php` includes `parts.php`, `mail.php`, then `pages_public.php` (returns its pages only), `vehicles.php`, `requests.php`, `finance_requests.php`, `standard.php`, `account.php` in one scope, each return kept as `$…_part`.
- Trap: `parts.php` keeps the sample vehicles in `$vehicles`; a part's return under that name would overwrite them.
- `requests.php` owns `privacy` and `email_reply` with its `email_comment` widget; `standard.php` owns `google_reviews` (added at assembly: the home placed it, no part defined it).

## How does it work without new product behaviour?
- A vehicle is a record of the Vehicles form, a collection ([[collection-fields]]): price, year, mileage and power number kinds, `photo_1`-`photo_4` image kinds, office fields `status` and `featured`. Lists filter `?make=`, `?body=`, `?fuel=`, `?condition=` ([[collection-lists]]) and leave out sold cars.
- Test drive, trade-in and finance requests are records of their own forms as Real Estate's viewing ([[design-template-realestate]]): the car from `?vehicle=` (`_cf.url_param`), office-only status, messages on the record page's Comments widget (`test_drive_messages`, `tradein_messages`, `finance_messages`), views `submitter_only`, staff lists. Trade-in photos go to the `private` folder. A finance request asks for bands (deposit, term, income), never an ID or bank number.
- Not built (new behaviour): a finance calculator or credit check, price-range filters, live stock feeds, online reservation with a payment, saved searches with alerts.

## What is the pdl- look?
- `pdl-brand`, `pdl-display`, `pdl-eyebrow`, `pdl-price` (heading font, tabular figures), `pdl-lift`. Two fonts (Barlow 300/600/700 headings, Inter body). Field ids `dl-`.
- The User Styles also ship the rules of the three `dealership-` blocks and `cta-sticky`.
- No `opacity-*` on text (the faded-text test).
- Turkish: check every shared key in tr.json before reuse; a wrong one gets the template's own key.

## Which tests cover it?
- `design_template_dealership_test.php` (data, shared parts) and `…_public_`, `…_vehicles_`, `…_requests_`, `…_finance_`, `…_standard_`, `…_account_test.php` load the parts; `…_assembly_test.php` the main file: offered without a shop, pages in order, widgets once and all placed, placeholders name pages and folders, contact search sections, `pdl-` classes equal rules, the home on the blocks, the five comment threads and the reply e-mail card, Google reviews, sample vehicles kept. It is in the shared lists of the collection-form (`vehicle_new`), account-email, submitter-name, consent-privacy (`dl-td-consent`, `dl-ti-consent`, `dl-fi-consent`) and faded-text tests.
