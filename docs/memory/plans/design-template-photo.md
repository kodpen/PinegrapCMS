---
name: design-template-photo
description: Plan: the Photography Studio design template (pinegrap-photo): galleries by category, session packages, shoot booking, a private client proofing area, gift vouchers; ~1,000-line steps
---

## What is it?
- A design template `pinegrap-photo` for a photography studio: home, galleries by category, the gallery page, session packages with prices, how a shoot works, about, testimonials, FAQ, shoot booking requests and a free studio time, a client area with private proofing galleries and downloads, proof selections, gift vouchers and session deposits in the shop, contact, blog, sign-in and a staff area. Started by a coordinator after the owner's list (`workflow/coordinator-no-plans.md`).
- Built like Hotel and Travel: `pinegrap-photo.php` plus the folder `pinegrap-photo/` (`parts.php`, `mail.php`, `pages_public.php`, `galleries.php`, `bookings.php`, `clients.php`, `shop.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-photo-design]].

## Steps (claim as `[plan:design-template-photo] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1: `parts.php` (node builders, the studio's data: categories, sample galleries, packages with prices and what they include, add-ons, the shoot steps, why-us points, gift vouchers, figures, testimonials, pick lists; shared parts: account bar, notice, header, footer, booking band, staff bar; gallery, package and testimonial cards, the photo grid, form fields, comment thread, blog parts); `tests/design_template_photo_test.php`.

- 2: `pages_public.php`: home (`home_galleries`, `home_posts`), `packages`, `experience` (how a shoot works), `about`, `faq`; `tests/design_template_photo_public_test.php`.

- 3: `galleries.php`: the Galleries collection (`galleries` by `?category=`, the `gallery` record page with its photos, `gallery_new`) and `book_online` (booking widget starter for studio mini sessions, privacy link); test.

- 4: `mail.php` (the e-mail builders) and `bookings.php`: shoot booking requests (`book` with `?package=` and `?category=`, `booking_view`, `my_bookings`, `staff_bookings`, `privacy`, `email_reply`); test.

- 5: `clients.php`: the client area (`client_area` in public; the `client_sample` private folder with the sample proofing gallery `proofing_sample` and its downloads), proof selections (`selection` with `?gallery=`, `selection_view`, `my_selections`, `staff_selections`); test.

- 6: `shop.php`: `vouchers`, product, cart, checkout, order, `email_order`, the catalog (a session deposit per package, gift vouchers, `shippable => false`); test.

- 7: `standard.php`: contact, inbox, conversation, newsletter, blog (Photo Journal), blog post, help search, error ([[design-templates-standard-pages]]); test.

- 8: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.

- 9: assembly: `pinegrap-photo.php` (order, search sections, look, fonts, `pph-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, booking-templates), `designer/design-template-photo.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
