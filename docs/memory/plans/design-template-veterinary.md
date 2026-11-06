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
- 1: `parts.php` (node builders, the practice's data and pick lists, shared parts, service, species, vet, plan and testimonial cards, form fields, comment thread, blog parts); `tests/design_template_veterinary_test.php`.

- 2: `pages_public.php`: home (`home_vets`, `home_posts` as widgets), `services` and one `service_<key>` per service, `pets`, `emergency`, `new_clients`, `health_plans`, `about`, `faq`, `location`; `tests/design_template_veterinary_public_test.php`.

- 3: `vets.php`: the Vets collection (`vets` by `?species=`, the `vet` page with its days and an appointment link `?vet=`, `vet_new`) and `book_online` (booking widget starter, privacy link, [[booking-templates]]); test.

- 4: `mail.php` (the e-mail builders) and `appointments.php`: appointment requests (`appointment` with `?service=`, `?vet=`, `?species=`; `appointment_view`, `my_appointments`, `staff_appointments`, `privacy`, `email_reply`, `email_appointment_new`, `email_appointment_received`) and the health plan sign-up (`plan_join` with `?plan=`, `plan_join_view`, `staff_plan_joins`, its two e-mails); test.

- 5: `shop.php`: shop pages (`shop`, product, cart, checkout, order, `email_order`, the catalog: the health plans and pet care products); test.

- 6: `standard.php`: contact, inbox, conversation, newsletter, Pet Care Articles (`blog`, filtered by `?species=`), blog post, help search, error ([[design-templates-standard-pages]]); test.

- 7: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.

- 8: assembly: `pinegrap-veterinary.php` (order, search sections, look, fonts, `pvt-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, booking-templates), `designer/design-template-veterinary.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The next free step is the lowest one not marked done. Models: `pinegrap-gym/`, `pinegrap-construction/`. The appointment reads `?service=` (`$service_titles`), `?vet=` (`$vet_names`) and `?species=` (`$species_titles`); the plan sign-up `?plan=` (`$plan_choices`). Service pages are `service_<key>` under `$services`. `_test_vt_page_keys()` in the step 1 test lists every page key of the finished template.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
