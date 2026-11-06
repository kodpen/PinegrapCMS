---
name: design-template-music
description: Plan: the Music Artist design template (pinegrap-music): tour dates, discography with tracks, merch shop, news, press kit, mailing list; parts in a folder; ~1,000-line steps
---

## What is it?
- A design template `pinegrap-music` for a music artist or band: home, tour dates, discography with a release page and its tracks, the band (members), videos, merch sold in the shop, news (the blog), a press kit with a booking enquiry for promoters, the mailing list, contact, sign-in and a staff area. Started by a coordinator after the owner's list (`workflow/coordinator-no-plans.md`).
- Built like Gym and Travel: `pinegrap-music.php` plus the folder `pinegrap-music/` (`parts.php`, `pages_public.php`, `releases.php`, `shows.php`, `mail.php`, `press.php`, `merch.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-music-design]].

## Steps (claim as `[plan:design-template-music] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1: `parts.php` (node builders, the artist's data: members, sample releases with tracks and durations, sample shows dated from today, cities, venues, ticket states, merch items with prices, press quotes, figures, links to listening services, pick lists; shared parts: account bar, notice, header, footer, mailing-list band, staff bar; release, track list, show row, member, merch and press-quote cards, form fields, comment thread, blog parts); `tests/design_template_music_test.php`.

- 2: `pages_public.php`: home (`home_shows`, `home_releases`, `home_posts`), `about` (story and members), `videos`, `faq`, `mailing_list` (the newsletter sign-up as its own page); `tests/design_template_music_public_test.php`.

- 3: `releases.php` and `shows.php`: the Releases collection (`discography` by `?format=`, the `release` page with the track list, credits, listening links and fans' words as comments, `release_new`) and the Shows collection (`tour` upcoming from today by `?country=`, `past_shows`, the `show` page with venue, doors, ticket link and state, `show_new`), `home_releases`, `home_shows`; test.

- 4: `mail.php` (the e-mail builders) and `press.php`: the press kit (`press`: bio in three lengths, photos, logos, quotes, technical rider and stage plot from a public `press` folder) and booking enquiries for promoters (`booking`, `booking_view`, `my_bookings`, `staff_bookings`, `privacy`, `email_reply`); test.

- 5: `merch.php`: shop pages (`merch`, product, cart, checkout, order, `email_order`, the catalog with merch products and a signed vinyl per release) and gift cards; test.

- 6: `standard.php`: contact, inbox, conversation, newsletter, News (the blog), blog post, help search, error ([[design-templates-standard-pages]]); test.

- 7: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.

- 8: assembly: `pinegrap-music.php` (order, search sections, look, fonts, `pmu-` rules, folders incl. `press`, contact group, group `newsletter`, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text), `designer/design-template-music.md`; the PR body names `[NEW] Added the Music Artist design template`; this plan and its design file are deleted.

## Where to start?
- The next free step is the lowest one not marked done. `pinegrap-travel/`, `pinegrap-gym/`, `pinegrap-events/` (dates from today), [[design-templates-file-format]], [[design-templates-catalog-and-look]].

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
