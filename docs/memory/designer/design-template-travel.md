---
name: design-template-travel
description: Travel Agency template (pinegrap-travel): tours and destinations as collections, reviews, booking requests and tailor-made enquiries, deposits and gift cards in the shop, ptv- look, tests.
---

## What does it ship?
- Home (places `home_tours`, `home_destinations`, `google_reviews`, `home_posts`), `about`, `travel_info`, `faq`, `deals`; `destinations`, `destination`, `tours`, the `tour` record page (itinerary, reviews), `destination_new`, `tour_new` (staff); `book`, `booking_view`, `my_bookings`, `staff_bookings`, `privacy`, `email_reply`; `enquiry` (`?destination=`), `enquiry_view`, `my_enquiries`, `staff_enquiries`; shop pages (`packages`, product, cart, checkout, order, `email_order`); the standard pages with the blog called Travel Journal ([[design-templates-standard-pages]]); sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`; the e-mail pages.
- Shared: account_bar, notice, header, footer, staff_bar, trip_band, email_header/footer. Order 83, icon `bi-airplane`, look `modern-soft`, palette `sky` (e-mail blue `#0369a1`). Folders root, public, registration, private. Contact group `newsletter`. `catalog` from `shop.php`: a Tour Packages group with a deposit per sample tour (`TRAVEL-DEPOSIT-<KEY>`) and the gift cards. Built in #7473–#7700 and the assembly PR.

## Why a main file plus a folder?
- As Hotel ([[design-template-hotel]]): `pinegrap-travel.php` includes `parts.php`, `mail.php`, then `pages_public`, `tours`, `bookings`, `enquiries`, `shop`, `standard`, `account` in one scope, returns kept as `$…_part`. `bookings.php` comes before `enquiries.php`, whose pages use its helpers.
- Trap: `parts.php` keeps the samples in `$tours`, `$destinations`, `$deals`, `$gift_cards`; no part may return into those names.
- Most search sections are marked where built (`$searchable()`); the main file marks only the contact page's Contact Details and Inbox Note.

## How does it work without new product behaviour?
- Destinations and tours are collections ([[collection-fields]]): a tour has destination and style pick lists, number kinds `days`, `group_size`, image kinds `photo_1`-`photo_3`, the price as words (`$money()`), departures as words (never a date), the itinerary one line per day, office field `featured`. Lists filter `?region=`, `?destination=`, `?style=` ([[collection-lists]]).
- Reviews are the tour page's comments with `rating` (`tour_reviews`, newest first, one answer level).
- A booking is a request, never a reservation: Booking Requests record (`?tour=` by `_cf.url_param`, departure with `min_date` today), office-only status, messages on the record page's Comments widget, `submitter_only` views, My and staff lists. Tailor-made enquiries the same way. Only shop pages and `address_book` carry `requires => ecommerce`; shared parts never link them.
- Not built: seat counts, prices per date, a balance invoice after the deposit, flight search, supplier sync.

## What is the ptv- look?
- `ptv-brand`, `ptv-display`, `ptv-eyebrow`, `ptv-price` (tabular figures), `ptv-quote`, `ptv-lift`; Poppins headings, Nunito Sans body. Field ids `tv-`.
- Turkish: "tur", "destinasyon", "kapora", "rezervasyon talebi", blog "Seyahat Günlüğü"; no shared key reading "randevu" (the bookings test checks it).

## Which tests cover it?
- `design_template_travel_test.php` (data, shared parts, `_test_tv_page_keys()`) and `…_public_`, `…_tours_`, `…_bookings_`, `…_enquiries_`, `…_shop_`, `…_standard_`, `…_account_test.php`; `…_assembly_test.php`: offered without the shop, catalog, pages in order, widgets once and placed, placeholders, shop-only `requires`, contact search sections, `ptv-` rules, six comment widgets (tour reviews not drawn: the shared draw goes two answers deep), reply e-mail card, samples kept, Google reviews.
- In the shared lists of the collection-form (`blog_new`, `destination_new`, `staff_entry`, `tour_new`), account-email, submitter-name, consent-privacy (`tv-bk-consent`, `tv-eq-consent`) and faded-text tests.
