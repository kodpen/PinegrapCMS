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
- 1 (done, #7473): `parts.php` (builders, data, shared parts, cards, form fields, comment thread); `tests/design_template_travel_test.php`.

- 2 (done, #7490): `pages_public.php`: home, `about`, `travel_info`, `faq`, `deals`.

- 3 (done, #7501): `tours.php`: the Destinations and Tours collections, their lists and record pages, `home_tours`, `home_destinations`.

- 4 (done, #7528): `mail.php` (the e-mail builders) and `bookings.php`: booking requests (`book` with `?tour=` and `?deal=`, `booking_view`, `my_bookings`, `staff_bookings`, `privacy`, `email_booking_new`, `email_booking_received`, `email_reply`); `tests/design_template_travel_bookings_test.php`.

- 5: `enquiries.php` (tailor-made enquiry: `enquiry`, `enquiry_view`, `my_enquiries`, `staff_enquiries`) and `shop.php` (`packages`, product, cart, checkout, order, `email_order`, the catalog with a deposit per tour and gift cards); test.

- 6: `standard.php`: contact, inbox, conversation, newsletter, blog (Travel Journal), blog post, help search, error ([[design-templates-standard-pages]]); test.

- 7: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.

- 8: assembly: `pinegrap-travel.php` (order, search sections, look, fonts, `ptv-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text), `designer/design-template-travel.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The next free step is the lowest one not marked done. The tour page's comments already name `{{tab:email_reply}}` (step 4 makes it); the booking form reads `?tour=` (tour page, deals table) and `?deal=` (deal cards) with `$booking_tour_choices` and `$deal_choices`. The tests share `_test_tv_page_keys()` and `_test_tv_shop_keys()` from `tests/design_template_travel_test.php`.
- `bookings.php` has the e-mail pages of the booking form and `email_reply`; `mail.php` gives `$mail_page`, `$mail_heading`, `$mail_para`, `$mail_button`, `$mail_line`, `$mail_box`, `$mail_greeting`, `$mail_settings` and the shared `email_header`/`email_footer` trees. Some shared booking keys read "randevu" in Turkish ("We received your booking request", "Booking Card"): use keys that say "rezervasyon" (the bookings test checks it).

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
