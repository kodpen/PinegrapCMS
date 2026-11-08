---
name: design-template-veterinary
description: Veterinary template (pinegrap-veterinary): services, pets, emergencies, vets as a collection, online booking, appointment and health plan requests, plans in the shop, pvt- look, tests.
---

## What does it ship?
- Public: `home` (`home_vets`, `home_posts`, `google_reviews` under Owner Stories), `services` and one `service_<key>` per service, `pets`, `emergency`, `new_clients`, `health_plans`, `about`, `faq`, `location`; `vets` (`?species=`), `vet`, `vet_new`, `book_online` ([[booking-templates]]); `appointment` (`?service=`, `?vet=`, `?species=`), `appointment_view`, `my_appointments`, `staff_appointments`; `plan_join` (`?plan=`), `plan_join_view`, `staff_plan_joins`; `privacy`, `email_reply` and the request e-mails; shop pages (`shop`, product, cart, checkout, order, `email_order`) with `requires => ecommerce`; the standard pages with Pet Care Articles (`blog`, `?species=`), account and staff pages. The full list is `_test_vt_page_keys()`.
- Catalog: the health plans (a year paid at once, no membership access) and pet care products; only shop pages and `address_book` need the shop.
- Shared: account_bar (emergency line), notice, header, footer, staff_bar, booking_band, email_header/footer. Order 125, icon `bi-heart-pulse`, look `playful`, palette `emerald`. Contact group `newsletter` (Veterinary Newsletter). Built in #7649 to #8074 and the assembly.

## Why a main file plus a folder?
- As Hotel ([[design-template-hotel]]): `pinegrap-veterinary.php` includes `parts.php`, `mail.php`, then `pages_public`, `vets`, `appointments`, `shop`, `standard`, `account` in one scope, returns kept as `$…_part` (`$services`, `$species`, `$vets`, `$plans`, `$testimonials` are parts.php's data).

## How does it work without new product behaviour?
- Services, pets, emergency signs, the first visit and the health plans are page content from parts.php's data; the plans page stands without the shop and links `plan_join?plan=`.
- Vets: a collection ([[collection-fields]]) with a photo as share image; the list filters `?species=`, the vet page links `appointment?vet=` and the articles by species.
- An appointment and a health plan sign-up are requests, never a confirmed slot: pick lists by `_cf.url_param`, the date with `min_date` today, consent linking `privacy`, office-only status, messages on the record page's Comments widget, `submitter_only` view, the account's and staff's lists. A free time is booked on `book_online` with the booking widget's starter.
- Not built: a pet record per owner, medical history, vaccination reminders, repeat prescriptions, plan instalments.

## What is the pvt- look?
- `pvt-brand`, `pvt-display`, `pvt-eyebrow`, `pvt-hero` (a soft primary wash), `pvt-lift`, `pvt-price` (tabular figures); Nunito headings, Nunito Sans body. Field and section ids `vt-`. No fixed date in the sample; no `opacity-*` on text.
- Turkish: "veteriner hekim", "evcil hayvan", "randevu", "sağlık planı", "acil"; the template is "Veteriner Kliniği".

## Which tests cover it?
- `design_template_veterinary_test.php` (data, shared parts, `_test_vt_page_keys()`, `_test_vt_shop_keys()`) and the per-part tests; `…_assembly_test.php`: page order equal to the plan's pages, widgets placed once, placeholders, `requires`, search sections, `pvt-` rules, four comment threads and the reply card, samples, Google reviews.
- In the shared lists of the booking-templates (`appointment`, `book_online`), collection-form (`blog_new`, `staff_entry`, `vet_new`), account-email, submitter-name, consent-privacy (`vt-ap-consent`, `vt-pj-consent`), faded-text and custom-form-rules (`vt-ap-date`, `vt-pj-start`) tests; the url-params test covers every template on its own.
