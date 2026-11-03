---
name: design-template-hotel
description: Plan for the Hotel template (pinegrap-hotel): rooms as a collection with rates, booking and event enquiries followed from the account, gift vouchers in the shop; parts in a folder; steps 1-9
---

## What is being built?
- A seaside hotel's site: rooms with type and view filters, a page per room, rates, offers, dining, spa, meetings and weddings, location, about, FAQ; booking and event enquiries followed from the account; gift vouchers in the shop; standard pages, blog "Hotel Journal", e-mails. Id `pinegrap-hotel`, 1.0.0, Bootstrap 5.3.8.

## How does it work without new product behaviour?
- A room is a record of the Rooms form, a collection ([[collection-fields]]): name, type, view, guests, size (number kinds), beds, rate (the "From … a night" words, written with `$money()` from `$room_rates`), summary, description, amenities, `photo_1`-`photo_3` (image kinds); office `featured`. Lists filter `?type=` and `?view=` ([[collection-lists]]); the room page is the record view.
- A booking is a request, never a reservation: a record of the Booking Requests form (`?room=`, `?plan=`, `?offer=` by `_cf.url_param`; dates with `min_date` today, guests, arrival time, wishes, consent). Status office-only (Request received, Confirmed, Waiting for you, Declined, Cancelled); messages on the record page's Comments widget (`booking_messages`); views `submitter_only`; staff list. An event enquiry the same way (`event_messages`).
- Gift vouchers are products of one catalog group, prices from `$vouchers`; only shop pages carry `requires => ecommerce`, and shared parts never link them.
- Not built (new behaviour): live availability, prices per date, a deposit, OTA sync, room stock, appointment-booking features.

## Why parts in a folder?
- As App Studio and Launch: steps 1-8 write `includes/design_templates/pinegrap-hotel/` (not globbed); step 9's main file includes them in one scope, each return kept as `$…_part`. Classes `pht-`, field ids `ht-`. Trap: `parts.php` keeps the samples in `$rooms` and `$vouchers`; no part may return into those names.
- Turkish: check shared keys in tr.json before reuse (`Process` reads İşlem).

## Which steps are there?
- 1 Done (#6216): `parts.php` (data, samples and shared parts); test `design_template_hotel_test.php`.
- 2 Done (#6283): `pages_public.php` (public pages; the home places `home_rooms`, `google_reviews`, `home_posts`).
- 3 Done (#6280): `rooms.php` (the Rooms collection, list, record page, `home_rooms`).
- 4 Done (#6282): `mail.php`, `bookings.php` (booking requests, their views, lists and e-mails).
- 5 Done (#6289). Events: `events.php` (`meetings`, `event_enquiry`, `enquiry_view`, `my_enquiries`, `staff_enquiries`, notify e-mails).
- 6 Done (#6287). Shop: `shop.php` (Gift Vouchers group, `vouchers`, product, cart, checkout, order, `email_order`).
- 7 Done (#6294). Standard: `standard.php` (contact, inbox, conversation, newsletter, blog "Hotel Journal", blog post, search, error); test `…_standard_test.php`.
- 8 Account and staff: `account.php` (sign-in, My Account, staff area, `blog_new` with samples, account e-mails); test `…_account_test.php`.
- 9 Assembly: `pinegrap-hotel.php` (order 76, icon `bi-buildings`, look `elegant`, palette `cocoa`, contact group `newsletter`, folders, `pht-` User Styles, search map), the shared tests' lists, `designer/design-template-hotel.md`, plan deleted; PR body names `[NEW] Added the Hotel design template`.

## Where to start?
- `pinegrap-appstudio/`, `pinegrap-launch/` (prices from one table, shop pages), `pinegrap-realestate/` (requests), [[design-templates-file-format]], [[design-template-threads]], [[collection-template-refs]]. Steps 2-8 may run in parallel after step 1. The next free step is the lowest one not marked done here.
