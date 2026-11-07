---
name: design-template-gym
description: Gym template (pinegrap-gym): classes and a timetable, trainers, plans, class booking and join requests, memberships in the shop with a members' area, pgy- look, tests.
---

## What does it ship?
- Home (`home_trainers`, `google_reviews`, `home_posts`), `classes`, `timetable`, `plans`, `facilities`, `about`, `faq`, `location`; `trainers`, the `trainer` record page, `trainer_new` (staff), `book_online`; `class_booking`, `booking_view`, `my_bookings`, `staff_bookings`, `join`, `join_view`, `staff_joins`, `privacy`, `email_reply`; shop pages (`memberships`, product, cart, checkout, order, `email_order`) and the members' area (`members`, `workouts`); the standard pages with the blog called Training Notes and the help page Member Help ([[design-templates-standard-pages]]); sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`; the e-mail pages.
- Built in #7472, #7487, #7546, #7571, #7629, #7661, #7715 and #7760 (assembly).

## Why a main file plus a folder?
- As Hotel ([[design-template-hotel]]): `pinegrap-gym.php` includes `parts.php`, `mail.php`, then `pages_public.php`, `trainers.php`, `classes.php`, `members.php`, `standard.php`, `account.php` in one scope, each return kept as `$…_part`.

## Which shipped features does it reuse? (no new schema, module or behaviour)
- Group class booking is a request, as the Hotel's booking: a Class Bookings record (class, day, time by `?class=`, `?day=`, `?time=`), an office-only status, messages on the record page's Comments widget, `submitter_only` view, My Class Bookings, a staff list. The booking widget is not used for classes: a booking service has capacity 1 (`commerce/bookings-model.md`).
- Personal training: `book_online` places the booking widget's starter beside a link to the request form ([[booking-templates]]).
- Trainers are a collection (the Rooms pattern), filtered by `?focus=`.
- Membership: the plans page links the join request (`?plan=`, a free trial pass as one answer) and stands without the shop. With the shop, membership products (`membership_days`) renew the buyer's membership and the `members` folder (access `membership`) holds the members' area, as Courses ([[design-template-courses]]). Only shop pages and `address_book` carry `requires => ecommerce`; shared parts never link them; My Account links the members' area only with the shop on.
- Not built (new behaviour): class capacity and waiting lists, check-in, recurring bookings, freezing a membership, payments per class.

## What is the pgy- look?
- `pgy-brand`, `pgy-display`, `pgy-eyebrow`, `pgy-hero`, `pgy-lift`, `pgy-price`; field ids `gy-`. Oswald headings, Inter body. Look `bold`, palette `crimson`, order 79, icon `bi-lightning-charge`. No `opacity-*` on text ([[design-template-text-contrast]]).
- Turkish: "ders" for a class, "antrenör" for a trainer, "üyelik" for a membership, "ders programı" for the timetable, "resepsiyon" for the front desk. An existing key may carry another template's word (`First visit` reads "İlk muayene"): check it before reuse.

## Which tests cover it?
- `design_template_gym_test.php` (data, shared parts) and `…_public_`, `…_trainers_`, `…_classes_`, `…_members_`, `…_standard_`, `…_account_test.php` load the parts; `…_assembly_test.php` the main file: offered without a shop requirement, catalog, pages in order, only shop pages and `address_book` need the shop, widgets once and all placed, the `members` folder's membership access, contact search sections, `pgy-` classes equal rules, the four comment threads and the reply e-mail card, Google reviews, sample trainers and plan products kept.
- In the shared lists of the collection-form (`trainer_new`), account-email, submitter-name, consent-privacy (`gy-cb-consent`, `gy-jn-consent`), faded-text and booking-templates (`class_booking` ↔ `book_online`, which link each other) tests.
