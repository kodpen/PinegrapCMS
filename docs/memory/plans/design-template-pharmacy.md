---
name: design-template-pharmacy
description: Plan: the Pharmacy design template (pinegrap-pharmacy): services, prescription requests, vaccinations and health checks, on-duty info, health products shop, health advice, team; ~1,000-line steps
---

## What is it?
- A design template `pinegrap-pharmacy` for a community pharmacy: home, services (prescriptions, vaccinations, health checks, medicine reviews, travel health, home delivery) with a page per service, health checks (blood pressure, blood sugar, cholesterol, weight) with prices, opening hours and on-duty information (when the pharmacy is on night or holiday duty and how to find the on-duty pharmacy when it is closed), prescription requests followed from the account, questions to the pharmacist, online booking of a vaccination or health check, the team (pharmacists), Health Advice (the blog), contact, sign-in and a staff area. With the shop: health products; never prescription medicine.
- Built like Veterinary and Gym: `pinegrap-pharmacy.php` plus the folder `pinegrap-pharmacy/` (`parts.php`, `mail.php`, `pages_public.php`, `team.php`, `requests.php`, `shop.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-pharmacy-design]].

## Steps (claim as `[plan:design-template-pharmacy] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #7838): `parts.php` (node builders, the pharmacy's data and pick lists, shared parts and cards, form fields, comment thread, staff bar, blog parts); `tests/design_template_pharmacy_test.php`.

- 2: `pages_public.php`: home (`home_team`, `home_posts` as widgets), `services` and one `service_<key>` per service (home delivery is `service_delivery`), `health_checks`, `on_duty`, `about`, `faq`, `location`; test.

- 3: `team.php`: the Pharmacists collection (`team` by `?service=`, `pharmacist`, `pharmacist_new`, `home_team`) and `book_online` ([[booking-templates]]); test.

- 4: `mail.php` and `requests.php`: prescription requests (`prescription` with `?service=` and `?pharmacist=`; `prescription_view`, `my_prescriptions`, `staff_prescriptions`, `privacy`, `email_reply`, `email_prescription_new`, `email_prescription_received`) and questions to the pharmacist (`ask` with `?topic=`, `ask_view`, `my_questions`, `staff_questions`, their two e-mails); test.

- 5: `shop.php`: shop pages (`shop`, product, cart, checkout, order, `email_order`, the catalog: the product ranges' health products); test.

- 6: `standard.php`: contact, inbox, conversation, newsletter, Health Advice (`blog`, filtered by `?topic=`), blog post, help search, error ([[design-templates-standard-pages]]); test.

- 7: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.

- 8: assembly: `pinegrap-pharmacy.php` (order, search sections, look, fonts, `prx-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, booking-templates), `designer/design-template-pharmacy.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The lowest step not marked done. Model: `pinegrap-veterinary/` (same shape). The prescription request's lists: `?service=` `$request_choices`, `?pharmacist=` `$pharmacist_choices`; the question's `?topic=` `$topic_choices` (the blog post sends its topic). Step 4 adds to `prescription` a "Book a vaccination or check" box linking `book_online` and ships `privacy` (named `lang('privacy-policy')`); step 6's blog filters `?topic=`. `_test_rx_page_keys()` (step 1 test) lists every page key; later tests `require_once` it.

## Open questions
- None: all reuses shipped features; new behaviour goes to the architect (Working mode 10).
