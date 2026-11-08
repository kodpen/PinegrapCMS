---
name: design-template-bikeshop
description: Plan: the Bike Shop design template (pinegrap-bikeshop): bikes and parts catalog, repair and service requests, bike fitting, group rides calendar, news; small steps
---

## What is it?
- `pinegrap-bikeshop`, a bicycle shop with a repair workshop: home, Workshop, Bike Fit, Sizes, Group Rides by date, Bikes and Parts (catalog and shop), service and fit requests, about, News (blog), contact, sign-in, staff area.
- File layout like Zoo and Ski Resort (main file plus folder `pinegrap-bikeshop/`, one file per step, `$…_part`; [[design-template-museum]]); never their look.
- Installer order 147 (up to 133 shipped or in assembly PRs, 145 Planetarium).

## What makes it unlike the shipped templates?
- Composition: the home opens as a workbench, not a slogan: a split first screen with the workshop's load this week (`bikeshop-workshop`) on one side and the newest bikes on the other, then the next group ride (`bikeshop-rides`), the fit band (`bikeshop-fit`), a parts strip by category and the news. No figures row, testimonials, ask band or FAQ accordion on the home.
- Signature pages: Workshop (the board, prices in `services-price-list`, the request one click away), Sizes, Bike Fit (packages in `pricing-tiers`), Group Rides (the week, then the calendar).
- Motion: jobs, rides and fit points rise on view, the fit photo fades in; nothing moves by itself (`props._fx` only).
- Type: Saira Condensed headings, Schibsted Grotesk body; no other template uses either.

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-bikeshop-design]].

## Steps (claim as `[plan:design-template-bikeshop] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #8179): the `bikeshop-` palette block family `assets/js/style_designer_blocks_bikeshop.js` (workshop board, frame sizes, group rides, bike fit), `tests/designer_blocks_bikeshop_test.php`, `designer/designer-blocks-bikeshop.md`.

- 2 (done, #8230): `parts.php` (data, pick lists, helpers); test.

- 3 (done, #8258): `cards.php`: shared parts (account bar, notice, header, footer, staff bar), record and data cards, form fields, comment thread, newsletter and blog parts; test.

- 4 (done, #8273): `pages_public.php`: home (`home_rides`, `home_posts` placed as widgets, `home_bikes` from the catalog), `workshop`, `sizes`, `fit`, `about`; places the `bikeshop-` blocks; test.

- 5 (done, #8283): `mail.php` and `requests.php`: service requests (`service_request`, `service_view`, `my_services`, `staff_services`), fit requests (`fit_request`, `fit_view`, `my_fits`, `staff_fits`), `book_online` ([[booking-templates]]), `privacy`, the request e-mails; test.

- 6 (done, #8298): `rides.php`: Group Rides by date (`rides`, `ride`, `ride_new`, `home_rides`); test.

- 7: `shop.php`: the catalog (Bikes, Parts, Accessories, Workshop Vouchers; SKUs `BKS-BIKE-`, `-PART-`, `-ACC-`, `-GIFT-`), product, cart, checkout, order, `email_order`; test.

- 8: `standard.php`: contact, inbox, conversation, newsletter, News (`blog`), blog post, help search, error ([[design-templates-standard-pages]]); test.

- 9: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the account e-mail pages; test.

- 10: assembly: `pinegrap-bikeshop.php` (order 147, search sections, look, fonts, `pbs-` rules, folders), assembly test, the shared test lists, `designer/design-template-bikeshop.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The lowest step not marked done. Layout models: `pinegrap-skiresort/`; `pinegrap-restaurant/` for dated events. Lists: `?job=` `$job_kinds`, `?bike=` `$bike_kinds`, `?fit=` `$fit_kinds`, `?pace=` `$pace_titles`. Never `?view=` ([[design-template-hotel]]).
- Steps 4-6 place the `bikeshop-` blocks ([[designer-blocks-bikeshop]]); their rules come through `pg_designer_block_css()`.

## Open questions
- None: it reuses shipped features; new behaviour goes to the architect.
