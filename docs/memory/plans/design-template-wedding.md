---
name: design-template-wedding
description: Plan: the Wedding Planner and Venue design template (pinegrap-wedding): services, packages, venue, real weddings, timeline, enquiries and visits; ~1,000-line steps
---

## What is it?
- A design template `pinegrap-wedding` for a wedding planner with its own venue: home, about and team, planning services and venue packages with prices, the venue's spaces with their capacity, a gallery, Real Weddings stories, the planning timeline from a year before to the day, FAQ, consultation enquiries and venue visits, testimonials with Google reviews, contact, blog, sign-in and a staff area. Started by a coordinator after the owner's list (`workflow/coordinator-no-plans.md`).
- Built like Hotel and Travel: `pinegrap-wedding.php` plus the folder `pinegrap-wedding/` (`parts.php`, `mail.php`, `pages_public.php`, `weddings.php`, `enquiries.php`, `shop.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-wedding-design]].

## Steps (claim as `[plan:design-template-wedding] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #7503): `parts.php` (data, shared parts, cards, form fields, comment thread, blog parts); test.
- 2 (done, #7585): `pages_public.php` (home, about, services, packages, venue, gallery, timeline, faq); test.
- 3 (done, #7616): `weddings.php` (`weddings`, `wedding`, `wedding_new`, `home_weddings`); test.
- 4 (in progress): `mail.php` (the e-mail builders) and `enquiries.php`: consultation enquiries (`enquiry` with `?package=`, `?service=`, `?space=`, `enquiry_view`, `my_enquiries`, `staff_enquiries`, `privacy`, `email_reply`) and `book_visit` (the booking widget's starter beside the enquiry, [[booking-templates]]); test.
- 5: `shop.php`: `shop`, product, cart, checkout, order, `email_order`, the catalog (a date-hold deposit per package, gift cards); test.
- 6: `standard.php`: contact, inbox, conversation, newsletter, blog (Wedding Journal), blog post, help search, error ([[design-templates-standard-pages]]); test.
- 7: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.
- 8: assembly: `pinegrap-wedding.php` (order, search sections, look, fonts, `pwd-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, booking-templates), `designer/design-template-wedding.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start (step 4)
- Read `pinegrap-wedding/parts.php` for the enquiry's pick lists (`$package_choices`, `$service_choices`, `$space_choices`, `$season_choices`, `$budget_choices`, `$ceremony_choices`, `$contact_choices`, `$enquiry_statuses`) and the form builders; the cards already link `{{page:enquiry}}?package=|service=|space=` and the Real Weddings page sends `?space=` (#7616), so the enquiry's fields read those names through `_cf.url_param`.
- Copy the shape of `pinegrap-travel/bookings.php` and `mail.php` (or Hotel's) for the request form, view, My Enquiries, the staff list, `privacy`, `email_reply`; `book_visit` follows [[booking-templates]] (booking widget `'tree' => 'starter'`, a link back to the enquiry).
- Turkish: add keys with `tools/tr_add.php --from=<json>`; when `development` moves, a conflict lands only in `tr/*.json`: take `development`'s shard and re-add the keys.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
