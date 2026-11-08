---
name: design-template-carrental
description: Car Rental template (pinegrap-carrental): the fleet as a collection, booking requests with extras and deals, business rentals, desks, extras, deals and terms as fixed content, pcr- look, tests.
---

## What does it ship?
- Home (`home_cars`, `google_reviews` under the renters' words, `home_posts`), `locations`, `extras`, `deals`, `terms`, `about`, `faq`; `fleet` (`?class=`, `?gearbox=` on field `transmission`, `?fuel=`), the `car` record page, `car_new` (staff); `book`, `booking_view`, `my_bookings`, `staff_bookings`, `privacy`, `email_reply`, `email_booking_new`, `email_booking_received`; `business`, `business_enquiry`, `enquiry_view`, `my_enquiries`, `staff_enquiries`, two enquiry e-mails; contact, inbox, conversation, newsletter, `blog` (the Road Trip Journal), `blog_post`, `search`, `error`; sign-in and account pages, `staff_directory`, `staff`, `blog_new`, `staff_entry`, the account e-mails.
- Built in #7646, #7903, #7933, #8062, #8084, #8101, #8134 and the assembly.

## Why a main file plus a folder?
- `pinegrap-carrental.php` includes `parts.php`, `pages_public.php` (returns its pages only; its home widgets come from `fleet.php` and `standard.php`), `fleet.php`, `mail.php`, `bookings.php`, then `business.php` and `standard.php` (both reuse `bookings.php`'s pieces) and `account.php`, in one scope, each return kept as `$…_part`; `$classes`, `$cars`, `$locations`, `$extras`, `$deals` are parts.php's data.
- Search sections are marked where built (`$searchable`); the main file adds none.

## Which shipped features does it reuse? (no new schema, module or behaviour)
- The fleet is a collection (the Vehicles pattern of Dealership, [[design-template-dealership]]): sample cars from `$cars`, shown "or similar".
- A booking is a request, never a reservation, as Hotel's ([[design-template-hotel]]): `?car=`, `?class=`, `?location=`, `?deal=`, pick-up and return days `min_date` today, extras as check boxes, office-only status, messages on the Comments widget, `submitter_only` view, own and staff lists. The price is sent by the desk in a message.
- Business rentals: an enquiry form the same way (`?plan=`, start date `min_date` today).
- Desks, extras, deals and terms are fixed content. No shop: only `address_book` carries `requires => ecommerce`; no catalog.
- Not built: live availability, a computed price, online deposit or payment, one-way fees, licence checks, damage reports, fleet stock per desk.

## What is the pcr- look?
- `pcr-brand`, `pcr-display`, `pcr-eyebrow`, `pcr-hero`, `pcr-lift`, `pcr-price`; field ids `cr-`. Archivo headings, Inter body. Look `modern-soft`, palette `amber`, order 131 (127 Brewery, 129 Film Studio, 133 Gallery), icon `bi-key`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date, year or month in the sample.
- Turkish: "araç kiralama" / "kiralık araç", "filo", "teslim alma" and "iade", "ek hizmet", "kampanya" (the shipped `Deals` page reads "Fırsatlar"), "rezervasyon talebi". `Bookings`, `Contact`, `Deposit`, `Doors`, `Available` mean something else in Turkish: the template uses `Booking Requests`, `Contact Us`, `Security deposit` and the like.

## Which tests cover it?
- `design_template_carrental_test.php` (data, shared parts, `_test_cr_page_keys()`) and `…_public_`, `…_fleet_`, `…_bookings_`, `…_business_`, `…_standard_`, `…_account_test.php` load the parts; `…_assembly_test.php` the main file: pages in order and equal to `_test_cr_page_keys()`, widgets placed once, folders, search sections, `pcr-` rules, the four comment threads, Google reviews, sample cars and posts seeded.
- In the shared lists of the collection-form (`blog_new`, `car_new`, `staff_entry`), account-email, submitter-name, consent-privacy (`cr-bk-consent`, `cr-be-consent`, `cr-contact-consent`), custom-form-rules (`cr-bk-pickup-date`, `cr-bk-return-date`, `cr-be-start`) and faded-text tests.
