---
name: design-template-planetarium
description: Plan: the Planetarium and Observatory design template (pinegrap-planetarium): dome shows by date, telescope nights, school visits, tickets and memberships, astronomy news; small steps
---

## What is it?
- `pinegrap-planetarium`, a planetarium with a public observatory: home, tonight, Dome Shows and Telescope Nights by date, visit, tickets, memberships, school visit requests, the observatory, astronomy news (blog), contact, FAQ, sign-in, staff area.
- File layout like Zoo and Ski Resort (main file plus folder `pinegrap-planetarium/`, one file per step, `$…_part`; [[design-template-museum]]); never their look.

## What makes it unlike the shipped templates?
- Composition: the home opens on a dark `planetarium-tonight` (the telescope status first, no hero slogan), then today's dome shows, the next telescope nights, the moon strip, news last. No figures row, testimonials, ask band or FAQ accordion on the home.
- Signature sections (family `planetarium-`, step 1): tonight's sky, dome showtimes by day, objects through the telescope, moon phases with the telescope nights.
- Light and dark by subject: night sections (tonight, moon, show heads) `data-bs-theme="dark"` with `bg-body text-body` ([[design-templates-catalog-and-look]]), day sections (visit, tickets, school visits) light.
- Motion: planets, showtimes and phases rise on view, objects fade in, the home's sky photo a slow parallax; nothing moves by itself (`props._fx`).
- Type: Sora headings, IBM Plex Sans body (unused by other templates), tabular times.

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-planetarium-design]].

## Steps (claim as `[plan:design-template-planetarium] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #8172): the `planetarium-` block family `assets/js/style_designer_blocks_planetarium.js`, its test, `designer/designer-blocks-planetarium.md`.
- 2 (done, #8216): `parts.php`: node builders, data, pick lists, `$money`, `$query`, `$show_on`, page shell, helpers; test.
- 3 (done, #8249): `cards.php`: shared parts (header, footer, account and staff bars), cards, form fields, comment thread, newsletter and blog parts; test.
- 4 (done, #8263): `pages_public.php`: home (`home_shows`, `home_nights`, `home_posts` placed as widgets), `tonight`, `visit`, `tickets`, `memberships`, `observatory`, `faq`; places the `planetarium-` blocks; test.
- 5 (done, #8277): `shows.php`: the Dome Shows collection by date (`shows` from today on, by `?audience=`, `show`, `show_new`, `home_shows`); test.
- 6 (done, #8285): `nights.php`: Telescope Nights by date (`nights`, `night` with its office status, `night_new`, `home_nights`); test.
- 7: `mail.php`, `visits.php`: visit requests (`visit_request` with `?visit=`, `?grade=`, `visit_view`, `my_visits`, `staff_visits`, `privacy`, the visit e-mails); test.
- 8: `shop.php`: catalog (SKUs `PLM-TICKET-`, `PLM-MEMBER-`), `buy_tickets`, product, cart, checkout, order mail; test.
- 9: `standard.php`: contact, inbox, conversation, newsletter, News (`blog`), post, search, error ([[design-templates-standard-pages]]); test.
- 10: `account.php`: sign-in, My Account, `staff`, `blog_new`, `staff_directory`, `staff_entry`, account e-mails; test.
- 11: assembly: `pinegrap-planetarium.php` (order 145, search sections, look, fonts, `ppm-` rules, folders), assembly test, the shared test lists, `designer/design-template-planetarium.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The lowest step not marked done. Layout models: `pinegrap-skiresort/`, `pinegrap-restaurant/` for dated events. Lists: `?audience=` `$audience_titles`, `?visit=` `$visit_choices`, `?grade=` `$grade_titles`. Never `?view=` ([[design-template-hotel]]).
- Steps 4-6 place the family's blocks ([[designer-blocks-planetarium]]).
- Order 145 is claimed here.

## Open questions
- None: all reuses shipped features (Working mode 10 for anything new).
