---
name: design-template-travel
description: Plan: the Travel Agency design template (pinegrap-travel): destinations, tours with itineraries, booking requests, shop deposits, reviews, enquiries; ~1,000-line steps
---

## What is it?
- A design template `pinegrap-travel` for a travel agency or tour operator: home, destinations, tour packages with a day-by-day itinerary page, prices and departures, booking requests, deposits sold in the shop, traveller reviews on each tour, tailor-made trip enquiries, travel information, FAQ, contact, blog, sign-in and a staff area. Started by a coordinator after the owner's list (`workflow/coordinator-no-plans.md`).
- Built like Hotel and Gym: `pinegrap-travel.php` plus the folder `pinegrap-travel/` (`parts.php`, `mail.php`, `pages_public.php`, `tours.php`, `bookings.php`, `enquiries.php`, `shop.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-travel-design]].

## Steps (claim as `[plan:design-template-travel] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #7473): `parts.php` (node builders, the agency's data: regions, destinations, travel styles, sample tours with itineraries and prices, the deposit, inclusions, deals, why-us points, gift cards, figures, testimonials, pick lists; shared parts: account bar, notice, header, footer, trip band, staff bar; destination, tour, deal and testimonial cards, tour facts, the itinerary as one line per day, form fields, comment thread, blog parts); `tests/design_template_travel_test.php`.

- 2 (done, #7490): `pages_public.php`: home (`home_tours`, `home_destinations`, `home_posts`), `about`, `travel_info`, `faq`, `deals`; `tests/design_template_travel_public_test.php`.

- 3: `tours.php`: the Destinations collection (`destinations` by `?region=`, `destination` with its tours, `destination_new`) and the Tours collection (`tours` by `?destination=` and `?style=`, the `tour` page with the itinerary, inclusions, departures and reviews as rated comments, `tour_new`); test.

- 4: `mail.php` (the e-mail builders) and `bookings.php`: booking requests (`book` with `?tour=`, `booking_view`, `my_bookings`, `staff_bookings`, `privacy`, `email_reply`); test.

- 5: `enquiries.php` (tailor-made enquiry: `enquiry`, `enquiry_view`, `my_enquiries`, `staff_enquiries`) and `shop.php` (`packages`, product, cart, checkout, order, `email_order`, the catalog with a deposit per tour and gift cards); test.

- 6: `standard.php`: contact, inbox, conversation, newsletter, blog (Travel Journal), blog post, help search, error ([[design-templates-standard-pages]]); test.

- 7: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.

- 8: assembly: `pinegrap-travel.php` (order, search sections, look, fonts, `ptv-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text), `designer/design-template-travel.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
