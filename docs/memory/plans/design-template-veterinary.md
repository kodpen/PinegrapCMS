---
name: design-template-veterinary
description: Plan: the Veterinary Practice design template (pinegrap-veterinary): services, vets, appointment requests, pet health plans, emergencies, pet care articles; ~1,000-line steps
---

## What is it?
- A design template `pinegrap-veterinary` for a small-animal veterinary practice: home, services (wellness exams, vaccinations, dentistry, surgery, diagnostics, nutrition) with a page per service, the pets it treats (dogs, cats, rabbits and small pets, birds), emergencies and out-of-hours care, a new clients' first visit page, the vets, pet health plans, appointment requests followed from the account, online booking of a free time, a health plan sign-up request, Pet Care Articles (the blog), contact, sign-in and a staff area. With the shop: the health plans and a few pet care products for sale. Not the Clinic template (people, doctors, check-up packages).
- Built like Gym and Construction: `pinegrap-veterinary.php` plus the folder `pinegrap-veterinary/` (`parts.php`, `mail.php`, `pages_public.php`, `vets.php`, `appointments.php`, `shop.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-veterinary-design]].

## Steps (claim as `[plan:design-template-veterinary] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #7649): `parts.php` (node builders, the practice's data and pick lists, shared parts, service, species, vet, plan and testimonial cards, form fields, comment thread, blog parts); `tests/design_template_veterinary_test.php`.

- 2 (done, #7800): `pages_public.php`: home (`home_vets`, `home_posts` as widgets), `services` and one `service_<key>` per service, `pets`, `emergency`, `new_clients`, `health_plans`, `about`, `faq`, `location`; `tests/design_template_veterinary_public_test.php`.

- 3 (done, #7810): `vets.php`: the Vets collection (`vets` by `?species=`, `vet`, `vet_new`, `home_vets`) and `book_online` ([[booking-templates]]); test.

- 4 (done, #7914): `mail.php` and `appointments.php`: appointment requests (`appointment` with `?service=`, `?vet=`, `?species=`; `appointment_view`, `my_appointments`, `staff_appointments`, `privacy`, `email_reply`, `email_appointment_new`, `email_appointment_received`) and the health plan sign-up (`plan_join` with `?plan=`, `plan_join_view`, `staff_plan_joins`, its two e-mails); test.

- 5 (done, #8003): `shop.php`: shop pages (`shop`, product, cart, checkout, order, `email_order`, the catalog: the health plans and pet care products); test.

- 6 (done, #8054): `standard.php`: contact, inbox, conversation, newsletter, Pet Care Articles (`blog`, filtered by `?species=`), blog post, help search, error ([[design-templates-standard-pages]]); test.

- 7: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.

- 8: assembly: `pinegrap-veterinary.php` (order, search sections, look, fonts, `pvt-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, booking-templates), `designer/design-template-veterinary.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The lowest step not marked done. Model: `pinegrap-gym/`. The appointment's lists: `?service=` `$reason_choices`, `?vet=` `$vet_choices` (the vet page sends the record's name), `?species=` `$pet_choices`; the sign-up `?plan=` `$plan_choices`. Step 4 adds to `appointment` a "Pick a free time now" box linking `book_online` and ships `privacy` (named `lang('privacy-policy')`); step 6's blog filters `?species=` (the vet page sends it). `_test_vt_page_keys()` (step 1 test) lists every page key; later tests `require_once` it.

## Open questions
- None: all reuses shipped features; new behaviour goes to the architect (Working mode 10).
