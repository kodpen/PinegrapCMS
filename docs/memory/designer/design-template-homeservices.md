---
name: design-template-homeservices
description: Home Services template (pinegrap-homeservices): services and team as collections, visit bookings and estimate requests, service areas, price guide, care plans in the shop, phs- look, tests.
---

## What does it ship?
- Home (`home_services`, `home_team`, `home_posts`, `google_reviews` under Testimonials), `about`, `areas` (visit days, call-out note, links to `book?area=`), `prices`, `faq`, `reviews`; `services` (`?category=`), `service` (rated reviews), `service_new`, `team`, `team_member`, `team_new`; `book` (`?service=`, `?area=`), `visit_view`, `my_visits`, `staff_visits`, `privacy`, `email_reply`; `estimate` (`?service=`, `?area=`, photos), `estimate_view`, `my_estimates`, `staff_estimates`; shop pages (`plans`, product, cart, checkout, order, `email_order`) with `requires => ecommerce`; the standard pages with the Tips blog, account and staff pages. The full list is `_test_hs_page_keys()`.
- Catalog: the Care Plans group (plans and gift cards, `shippable => false`); only shop pages and `address_book` need the shop.
- Shared: account_bar, notice, header, footer, staff_bar, visit_band, email_header/footer. Order 113, icon `bi-tools`, look `modern-sharp`, palette `steel`. Contact group `newsletter` (Home Services Newsletter). Built in #7717 to #8067 and the assembly.

## Why a main file plus a folder?
- As Hotel ([[design-template-hotel]]): `pinegrap-homeservices.php` includes `parts.php`, `mail.php`, then `pages_public`, `services`, `visits`, `estimates`, `shop`, `standard`, `account` in one scope, returns kept as `$…_part` (`$services`, `$areas`, `$team`, `$care_plans`, `$testimonials` are parts.php's data).

## How does it work without new product behaviour?
- Services: a collection ([[collection-fields]]) with category, summary, duration as words, crew number, price as words from `$money()`, what is included, photo and office `featured`; lists filter `?category=` ([[collection-lists]]). Reviews are the service page's comments with stars, answered one level deep.
- Team: a collection (name, role, years, summary, photo), no link to site users.
- A visit booking and an estimate request are requests, never a confirmed slot: service and area by `_cf.url_param`, the visit day with `min_date` today and a time window pick list, consent linking `privacy`, office-only status, messages on the record page's Comments widget (the estimate itself is a staff message), `submitter_only` view, the account's and staff's lists. Estimate photos go to the `private` folder.
- Areas, visit days, call-out notes, the price guide, why-us and how-it-works are page content from parts.php's data.
- Not built: live slot availability, a technician calendar, a postcode checker, price calculators, paying a visit online, recurring visits.

## What is the phs- look?
- `phs-brand`, `phs-display`, `phs-eyebrow`, `phs-lift`, `phs-price` (tabular figures), `phs-quote`; Manrope headings, Source Sans 3 body. Field and section ids `hs-`. No fixed date in the sample (visit days are words); no `opacity-*` on text.
- Turkish: "hizmet", "ziyaret" (a booking is "ziyaret talebi", never "rezervasyon"), "fiyat teklifi", "hizmet bölgesi", "ekip", "usta", "bakım planı".

## Which tests cover it?
- `design_template_homeservices_test.php` (data, shared parts, `_test_hs_page_keys()`, `_test_hs_shop_keys()`) and the per-part tests; `…_assembly_test.php`: page order equal to the plan's pages, widgets placed once, placeholders, `requires`, search sections, `phs-` rules, five comment threads and the reply card (the one-level reviews skip the shared draw's depth check, as Travel's tour reviews), samples, Google reviews.
- In the shared lists of the collection-form (`blog_new`, `service_new`, `staff_entry`, `team_new`), account-email, submitter-name, consent-privacy (`hs-bk-consent`, `hs-es-consent`), faded-text and custom-form-rules (`hs-bk-day`) tests; the url-params test covers every template on its own.
