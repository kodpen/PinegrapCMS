---
name: design-template-brewery
description: Plan: the Craft Brewery design template (pinegrap-brewery): beers, taproom events, brewery tour bookings, a shop and the Beer Club, built from existing features in ~1,000-line steps
---

## What is it?
- A design template `pinegrap-brewery` for a craft brewery with a taproom: home, beers, the taproom, taproom events, tour requests and private tastings, a shop, the Beer Club (membership terms, a members' area), about, FAQ, News, contact, sign-in and a staff area. Not `pinegrap-cafe` (a coffee shop).
- Built like Gym and Music: `pinegrap-brewery.php` plus the folder `pinegrap-brewery/` (`parts.php`, `pages_public.php`, `beers.php`, `events.php`, `mail.php`, `tours.php`, `shop.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-brewery-design]].

## Steps (claim as `[plan:design-template-brewery] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1: `parts.php` (builders; data: beers, styles, events dated by `$event_on()`, tours, taproom, shop products, club terms, pick lists; shared parts and cards, list helpers `$bw_*`); `tests/design_template_brewery_test.php`.

- 2: `pages_public.php`: home (`home_beers`, `home_events`, `home_posts`), `taproom` (hours, food, tap list as page content, getting there), `about` (story, the brewhouse, the team), `faq`; `tests/design_template_brewery_public_test.php`.

- 3: `beers.php` and `events.php`: the Beers collection (`beers` by `?style=`, the `beer` page with ABV, bitterness, tasting notes, food pairing and drinkers' notes as comments with stars, `beer_new`) and the Taproom Events collection (`events` from today by `?kind=`, `past_events`, the `event` page, `event_new`), `home_beers`, `home_events`; page names `our-beers`/`beer`, forms `Brewery Beers`/`Taproom Events`; test.

- 4: `mail.php` (the e-mail builders) and `tours.php`: the tours page (`tours`), tour booking requests (`tour_booking` with `?tour=`, `booking_view`, `my_bookings`, `staff_bookings`, `privacy`, `email_reply`, `email_booking_new`, `email_booking_received`) and `book_online` (booking widget starter for private tastings); test.

- 5: `shop.php`: shop pages (`shop`, product, cart, checkout, order, `email_order`, the catalog with beer packs, glassware, merch, gift cards and club terms with `membership_days`) and the Beer Club (`club` with the terms, the members' area in a `club` folder with access `membership`: `club_home`, `club_releases`); test.

- 6: `standard.php`: contact, inbox, conversation, newsletter, News (the blog), blog post, help search, error ([[design-templates-standard-pages]]); test.

- 7: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.

- 8: assembly: `pinegrap-brewery.php` (order, search sections, look, fonts, `pbw-` rules, folders incl. `club`, contact group, group `newsletter`, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, booking-templates), `designer/design-template-brewery.md`; the PR body names `[NEW] Added the Craft Brewery design template`; this plan and its design file are deleted.

## Where to start?
- The next free step is the lowest one not marked done. Copy the shapes from `pinegrap-music/` (collections, dates from today, request records) and `pinegrap-gym/` (`book_online`, membership products, members' folder); [[design-templates-file-format]], [[design-templates-catalog-and-look]].

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
