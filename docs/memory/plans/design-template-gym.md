---
name: design-template-gym
description: Plan: the Gym design template (pinegrap-gym), a fitness studio with classes, a timetable, trainers, membership plans, class booking and contact, built from existing features in ~1,000-line steps
---

## What is it?
- A design template `pinegrap-gym` for a fitness gym or studio: home, classes, the weekly timetable, trainers, membership plans, facilities, class booking requests, personal training booked at a free time, memberships sold in the shop, a members' area, contact, blog, sign-in and a staff area. The next template after the owner's list (`workflow/coordinator-no-plans.md`).
- Built like Hotel and Salon: `pinegrap-gym.php` plus the folder `pinegrap-gym/` (`parts.php`, `mail.php`, `pages_public.php`, `trainers.php`, `classes.php`, `members.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-gym-design]].

## Steps (claim as `[plan:design-template-gym] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #7469): `parts.php` (node builders, the gym's data: classes, timetable, trainers, plans, facilities, testimonials, pick lists; shared parts: account bar, notice, header, footer, join band, staff bar; class, trainer, plan and testimonial cards, form fields, comment thread, blog parts); `tests/design_template_gym_test.php`.

- 2: `pages_public.php`: home, `classes`, `timetable`, `plans`, `facilities`, `about`, `faq`, `location`; `tests/design_template_gym_public_test.php`.

- 3: `trainers.php`: the Trainers collection (`trainers`, `trainer`, `trainer_new`) and `book_online` (booking widget starter, privacy link); test.

- 4: `mail.php` (the e-mail builders) and `classes.php`: class booking requests (`class_booking`, `booking_view`, `my_bookings`, `staff_bookings`, `privacy`, `email_reply`) and the join request (`join`, `join_view`, `staff_joins`); test.

- 5: `members.php`: shop pages (`memberships`, product, cart, checkout, order, `email_order`, the catalog with membership products) and the members' area (`members`, `workouts`); test.

- 6: `standard.php`: contact, inbox, conversation, newsletter, blog, blog post, help search, error ([[design-templates-standard-pages]]); test.

- 7: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.

- 8: assembly: `pinegrap-gym.php` (order, search sections, look, fonts, `pgy-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, booking-templates), `designer/design-template-gym.md`; the PR body names the `[NEW]` line; this plan is deleted.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
