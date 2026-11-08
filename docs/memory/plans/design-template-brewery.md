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
- 1 (done, #7650): `parts.php` (data, shared parts, cards, list helpers `$bw_*`); `tests/design_template_brewery_test.php`.

- 2 (done, #7905): `pages_public.php`: home (`home_beers`, `home_events`, `home_posts`), `taproom` (hours, food, tap list as page content, getting there), `about` (story, the brewhouse, the team), `faq`; `tests/design_template_brewery_public_test.php`.

- 3 (done, #7934): `beers.php` (form `Brewery Beers`: `beers` by `?style=`, `beer` with drinkers' notes as comments with stars, `beer_new`, `home_beers`) and `events.php` (form `Taproom Events`: `events` from today by `?kind=`, `past_events`, `event`, `event_new`, `home_events`); `tests/design_template_brewery_collections_test.php`.

- 4 (done, #8045): `mail.php` and `tours.php` (tours, tour booking requests, `book_online`, privacy, booking e-mails); `tests/design_template_brewery_tours_test.php`.

- 5 (done, #8061): `shop.php` (shop pages, catalog with packs, merch, gift cards and club terms with `membership_days`; `club`, members' area `club_home`, `club_releases` in folder `club`); `tests/design_template_brewery_shop_test.php`.

- 6: `standard.php`: contact, inbox, conversation, newsletter, News (the blog), blog post, help search, error ([[design-templates-standard-pages]]); test.

- 7: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.

- 8: assembly: `pinegrap-brewery.php` (order, search sections, look, fonts, `pbw-` rules, folders incl. `club`, contact group, group `newsletter`, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, booking-templates), `designer/design-template-brewery.md`; the PR body names `[NEW] Added the Craft Brewery design template`; this plan and its design file are deleted.

## Where to start?
- The next free step is the lowest one not marked done. The tests share `_test_bw_page_keys()` and `_test_bw_shop_keys()` (`tests/design_template_brewery_test.php`); the beer card binds `picture`, `name`, `style`, `strength`, `summary`, `availability`, the event row `event_date`, `title`, `kind`, `starts`, `entry`, `ticket_url`; the newsletter band is `$news_band` (shared `news_band`). Copy the shapes from `pinegrap-music/` (collections, dates from today, request records) and `pinegrap-gym/` (`book_online`, membership products, members' folder); [[design-templates-file-format]], [[design-templates-catalog-and-look]].

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
