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
- 4 (done, #7634): `mail.php` (the e-mail builders) and `enquiries.php`: consultation enquiries (`enquiry` with `?package=`, `?service=`, `?space=`, `enquiry_view`, `my_enquiries`, `staff_enquiries`, `privacy`, `email_reply`) and `book_visit` (the booking widget's starter beside the enquiry, [[booking-templates]]); test.
- 5 (done, #7681): `shop.php`: `shop`, product, cart, checkout, order, `email_order`, the catalog (a date-hold deposit per package, gift cards); test.
- 6 (in progress): `standard.php`: contact, inbox, conversation, newsletter, blog (Wedding Journal), blog post, help search, error ([[design-templates-standard-pages]]); test.
- 7: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.
- 8: assembly: `pinegrap-wedding.php` (order, search sections, look, fonts, `pwd-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, booking-templates), `designer/design-template-wedding.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start (step 6)
- `pinegrap-wedding/shop.php` (#7681) returns `catalog` (group `wedding_shop`: a date-hold deposit per package, the gift cards) and the widget `cart_link` the account bar places; step 8 adds the catalog to the template file.
- Copy the shape of `pinegrap-hotel/standard.php` ([[design-templates-standard-pages]]): `contact`, `inbox`, `conversation`, `newsletter`, `blog` (Wedding Journal), `blog_post`, `search`, `error`; `email_reply` is already in `enquiries.php`, so its text names conversations and Wedding Journal posts. The blog parts (`$post_card`, `$ai_summary`, `$newsletter_line`, `$newsletter_consent`) are in `parts.php`.
- Turkish: add keys with `tools/tr_add.php --from=<json>`; when `development` moves, a conflict lands only in `tr/*.json`: take `development`'s shard and re-add the keys.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
