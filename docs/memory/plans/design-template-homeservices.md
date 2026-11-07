---
name: design-template-homeservices
description: Plan: the Home Services design template (pinegrap-homeservices): services, price guide, estimates, visit bookings, service areas, team, reviews; ~1,000-line steps
---

## What is it?
- A template for a home services company (cleaning, plumbing, electrical work, repairs): home, the services by category with a page per service, a price guide, free estimate requests with photos, visit bookings with a day and a time window, the service areas, the team, customer reviews on each service, FAQ, contact, a tips blog, sign-in and a staff area. Started by a coordinator (`workflow/coordinator-no-plans.md`).
- Built like Travel and Gym: `pinegrap-homeservices.php` plus the folder `pinegrap-homeservices/` (`parts.php`, `mail.php`, `pages_public.php`, `services.php`, `visits.php`, `estimates.php`, `shop.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-homeservices-design]].

## Steps (claim as `[plan:design-template-homeservices] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #7717): `parts.php` (builders, the company's data and pick lists, shared parts, service, area, team and review cards, form fields, comment thread, staff and blog parts); `tests/design_template_homeservices_test.php`.

- 2: `pages_public.php`: home, `about`, `areas` (each area with its visit days and call-out note, links to `book?area=`), `prices` (the price guide from the sample services), `faq`, `reviews` (testimonials, the `google_reviews` widget placed); test.

- 3: `services.php`: the Services collection (`services` by `?category=`, the `service` page with rated reviews, `service_new`, `home_services`) and the Team collection (`team`, `team_member`, `team_new`, `home_team`); test.

- 4: `mail.php` (the e-mail builders) and `visits.php`: `book` (with `?service=`, `?area=`), `visit_view`, `my_visits`, `staff_visits`, `privacy`, `email_reply`, the visit e-mails; test.

- 5: `estimates.php` (`estimate` with `?service=`, `?area=`, photos; `estimate_view`, `my_estimates`, `staff_estimates`, the estimate e-mails) and `shop.php` (`plans`, product, cart, checkout, order, `email_order`; the Care Plans group: plans and gift cards, `shippable => false`); test.

- 6: `standard.php`: contact, inbox, conversation, newsletter, Tips (the blog), post, help search, error, the message and welcome e-mails, `home_posts`, `google_reviews` ([[design-templates-standard-pages]]); test.

- 7: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.

- 8: assembly: `pinegrap-homeservices.php` (order, search sections, look, fonts, `phs-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, url-params), `designer/design-template-homeservices.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The next free step is the lowest one not marked done. Model files: `pinegrap-travel/` (collections, requests, enquiries, shop), `pinegrap-cafe.php` (`min_date`, `required_when`). Later tests share `_test_hs_page_keys()` and `_test_hs_shop_keys()` from `tests/design_template_homeservices_test.php`.
- Check a shared key's Turkish before reuse ("Booking" reads "rezervasyon" in Travel keys; a visit is "ziyaret"). PHP 7.1: no trailing comma after a call's last argument.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
