---
name: design-template-coworking
description: Coworking template (pinegrap-coworking): spaces and events as collections, tour, office and event requests, meeting rooms booked online, memberships in the shop, pcw- look, tests.
---

## What does it ship?
- Home (`home_spaces`, `home_events`, `google_reviews`, `home_posts`), `plans`, `amenities`, `about`, `faq`, `location`; `spaces` (`?kind=`), the `space` record page, `space_new` (staff), `book_online`; `tour`, `tour_view`, `my_tours`, `staff_tours`, `office_enquiry`, `enquiry_view`, `staff_enquiries`, `privacy`, `email_reply`; `events` (from today on, `?topic=`), `event`, `event_new`, `event_signup` (`?event=`), `signup_view`, `my_signups`, `staff_signups`; shop pages (`memberships` with Memberships and Day Passes groups, product, cart, checkout, order, `email_order`) and the members' area (`members`, `house_guide`); the standard pages with the blog called Blog and the help page Help Center ([[design-templates-standard-pages]]); sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`; the e-mail pages.
- Built in #7660–#8056 (eight steps) and the assembly.

## Why a main file plus a folder?
- `pinegrap-coworking.php` includes `parts.php`, `mail.php`, then `pages_public.php`, `spaces.php`, `requests.php`, `events.php` (reuses the request pieces of `requests.php`), `members.php`, `standard.php`, `account.php` in one scope, each return kept as `$…_part`; `$spaces`, `$plans`, `$events` are parts.php's data.

## Which shipped features does it reuse? (no new schema, module or behaviour)
- Spaces and events are collections: samples seeded from parts.php, events dated from today on.
- Meeting rooms by the hour: `book_online` places the booking widget's starter (a room is a booking service of capacity 1, `commerce/bookings-model.md`); it and `tour` link each other ([[booking-templates]]).
- Tours, office enquiries and event sign-ups are requests as the Gym's ([[design-template-gym]]): `?plan=` / `?space=` / `?event=`, tour date `min_date` today, office-only status, messages on the Comments widget, `submitter_only` view, own and staff lists.
- Membership: the plans page links the tour and stands without the shop. With the shop, products (`membership_days` 1 for a day pass, else 30) renew the buyer's membership and the `members` folder (access `membership`) holds the members' area, as Courses ([[design-template-courses]]). Only shop pages and `address_book` carry `requires => ecommerce`; My Account links the members' area only with the shop on.
- Not built: live desk availability, door access, meeting room credits, hourly invoicing, member profiles, virtual office mail.

## What is the pcw- look?
- `pcw-brand`, `pcw-display`, `pcw-eyebrow`, `pcw-hero`, `pcw-lift`, `pcw-price`; field ids `cw-`. Space Grotesk headings, Inter body. Look `modern-sharp`, palette `coral`, order 115 (99, 111, 113 taken), icon `bi-laptop`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date, year or month in the sample.
- Turkish: "ortak çalışma alanı", "masa" (hot desk "esnek masa", fixed desk "sabit masa"), "toplantı odası", "üyelik", "günlük bilet" (the shipped `Day Pass`), "tur", "etkinlik". `Space` reads "Boşluk" and `Seats` "Koltuklar": use `Workspace` and `Number of Seats`.

## Which tests cover it?
- `design_template_coworking_test.php` (data, shared parts) and `…_public_`, `…_spaces_`, `…_requests_`, `…_events_`, `…_members_`, `…_standard_`, `…_account_test.php` load the parts; `…_assembly_test.php` the main file: pages in order, shop pages, widgets placed once, folders, search sections, `pcw-` rules, the five comment threads, Google reviews, samples and products kept.
- In the shared lists of the collection-form (`blog_new`, `event_new`, `space_new`, `staff_entry`), account-email, submitter-name, consent-privacy (`cw-tr-consent`, `cw-oe-consent`, `cw-su-consent`), custom-form-rules (`cw-tr-date`), faded-text and booking-templates (`tour` ↔ `book_online`) tests.
