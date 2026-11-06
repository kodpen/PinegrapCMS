---
name: design-template-hotel
description: Hotel template (pinegrap-hotel): rooms as a collection with rates, booking requests and event enquiries followed from the account, gift vouchers in the shop, pht- look, tests.
---

## What does it ship?
- Home (featured rooms `home_rooms`, plans, offers, testimonials with `google_reviews`, `home_posts`), `about`, `dining`, `spa`, `location`, `offers`, `rates`, `faq`; `rooms`, the `room` record page, `room_new` (staff); `book`, `booking_view`, `my_bookings`, `staff_bookings`, `privacy`, `email_reply`; `meetings`, `event_enquiry`, `enquiry_view`, `my_enquiries`, `staff_enquiries`; shop pages (`vouchers`, product, cart, checkout, order, `email_order`); the standard pages with the blog called Hotel Journal ([[design-templates-standard-pages]]); sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`; the e-mail pages.
- Shared: account_bar, notice, header, footer, staff_bar, booking_band, email_header/footer. Order 76, icon `bi-buildings`, look `elegant`, palette `cocoa` (the e-mails' inline browns). Contact group `newsletter` (Hotel Newsletter). `catalog`: the Gift Vouchers group, a product per `$vouchers` entry. Built in #6216, #6283, #6280, #6282, #6289, #6287, #6294, #6299 and the assembly PR.

## Why a main file plus a folder?
- As Dealership and Launch: `pinegrap-hotel.php` includes `parts.php`, `mail.php`, then `pages_public.php`, `rooms.php`, `bookings.php`, `events.php`, `shop.php`, `standard.php`, `account.php` in one scope, each return kept as `$…_part`.
- Trap: `parts.php` keeps the samples in `$rooms` and `$vouchers`; a part's return under those names would overwrite them. `events.php` names its helpers `ev_…` so it never replaces a `bookings.php` helper.

## How does it work without new product behaviour?
- A room is a record of the Rooms form, a collection ([[collection-fields]]): guests and size number kinds, `photo_1`-`photo_3` image kinds, the rate as words written with `$money()` from `$room_rates`, office field `featured`. Lists filter `?type=` and `?room_view=` ([[collection-lists]]); `?view=` is reserved, so a filter on it was dropped and listed every room (#7514). `design_templates_url_params_test.php` checks all templates' names.
- A booking is a request, never a reservation: a record of Booking Requests (`?room=`, `?plan=`, `?offer=` by `_cf.url_param`, dates with `min_date` today), office-only status, messages on the record page's Comments widget (`booking_messages`), views `submitter_only`, a staff list. Event enquiries the same way (`event_messages`, `?space=`, `?kind=`); a guest's file goes to the `private` folder.
- Only the shop pages and `address_book` carry `requires => ecommerce`; the shared parts never link them.
- Not built (new behaviour): live availability, prices per date, a deposit, OTA sync, room stock, appointment-booking features.

## What is the pht- look?
- `pht-brand`, `pht-display`, `pht-eyebrow`, `pht-price` (tabular figures), `pht-quote` (serif italics), `pht-lift`; two fonts (Playfair Display headings, DM Sans body). Field ids `ht-`.
- No `opacity-*` on text: the booking band's line dropped `opacity-75` at assembly (the faded-text test).
- Turkish: check every shared key in tr.json before reuse (`Process` reads İşlem); a wrong one gets the template's own key.

## Which tests cover it?
- `design_template_hotel_test.php` (data, shared parts) and `…_public_`, `…_shop_`, `…_standard_`, `…_account_test.php` load the parts; `…_assembly_test.php` the main file: offered without a shop requirement, catalog present, pages in order, only shop pages need the shop, widgets once and all placed, placeholders name pages and folders, contact search sections, `pht-` classes equal rules, the four comment threads and the reply e-mail card, Google reviews, sample rooms and vouchers kept. It is in the shared lists of the collection-form (`room_new`), account-email, submitter-name, consent-privacy (`ht-bk-consent`, `ht-ev-consent`) and faded-text tests.
