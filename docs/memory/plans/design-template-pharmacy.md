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
- 1 (done, #7838): `parts.php`; test.

- 2 (done, #7909): `pages_public.php` (home delivery is `service_delivery`); test.

- 3 (done, #7928): `team.php` (Pharmacists collection, `book_online`); test.

- 4 (done, #7984): `mail.php`, `requests.php` (prescription requests, questions to the pharmacist, `privacy`, `email_reply`); test.

- 5 (done, #8005): `shop.php`; test.

- 6 (done, #8025): `standard.php`; test.

- 7 (done, #8051): `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.

- 8: assembly: `pinegrap-pharmacy.php` (order, search sections, look, fonts, `prx-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, booking-templates), `designer/design-template-pharmacy.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The lowest step not marked done. Model: `pinegrap-veterinary/` (same shape); Veterinary has no shop, standard or account part, so `shop.php` followed `pinegrap-market/`, `standard.php` `pinegrap-gym/`, and step 7 follows `pinegrap-gym/account.php`.
- Step 7: the post form (`blog_new`) needs a `topic` select whose answers are `$topic_titles`: Health Advice's filter (`blog_list`, `?topic=`) and the article's eyebrow and "Ask a Pharmacist" link (`?topic=` from the record) read the field `topic` (#8025).
- Step 8: `standard.php` also ships `email_new_message`, `email_message_received` and `email_welcome`, which `_test_rx_page_keys()` does not list yet; the shop's catalog is `shop.php`'s `catalog`. The prescription request's lists: `?service=` `$request_choices`, `?pharmacist=` `$pharmacist_choices`; the question's `?topic=` `$topic_choices` (the blog post sends its topic). Step 4 adds to `prescription` a "Book a vaccination or check" box linking `book_online` and ships `privacy` (named `lang('privacy-policy')`); step 6's blog filters `?topic=`. `_test_rx_page_keys()` (step 1 test) lists every page key; later tests `require_once` it. The parts' data and helpers are in `parts.php` (`$tel`, `$service_action`, `$record_link` in `team.php`); Turkish keys only with `php tools/tr_add.php`, word rules (eczane, eczacı, reçete, nöbet, aşı) are checked by every part's test, page addresses excepted.

## Open questions
- None: all reuses shipped features; new behaviour goes to the architect (Working mode 10).
