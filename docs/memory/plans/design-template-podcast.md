---
name: design-template-podcast
description: Plan: the Podcast Network design template (pinegrap-podcast): podcasts and episodes as collections, hosts, sponsorship enquiries, supporters' membership with bonus episodes; ~1,000-line steps
---

## What is it?
- A design template `pinegrap-podcast` for a podcast network (a studio publishing several podcasts): home, podcasts and episodes (notes, guests, chapters, transcript, comments), hosts, where to listen, sponsorship enquiries followed from the account, supporters' membership with bonus episodes, a guest pitch, News, newsletter, contact, sign-in, staff area.
- Built like Music and School: `pinegrap-podcast.php` plus the folder `pinegrap-podcast/` (`parts.php`, `mail.php`, `pages_public.php`, `podcasts.php`, `episodes.php`, `sponsors.php`, `supporters.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-podcast-design]].

## Steps (claim as `[plan:design-template-podcast] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #7653): `parts.php` (builders, data, shared parts and cards; episodes dated back from today by `$aired_on()`; tokens `cover`, `category`, `hosted_by`, `schedule`, `aired`, `podcast`, `episode_number`, `length`, `chapters`, `audio`, `listen_url`); `tests/design_template_podcast_test.php`.

- 2 (done, #7812): `pages_public.php`: home (`home_episodes`, `home_podcasts`, `home_posts`), `about` (the network and its hosts), `hosts`, `listen` (apps and feeds), `faq`; `tests/design_template_podcast_public_test.php`.

- 3: `podcasts.php` and `episodes.php`: the Podcasts collection (`podcasts` by `?category=`, the `podcast` page with its episodes by the reference, `podcast_new`) and the Episodes collection (`episodes` newest first by `?podcast=` and `?season=`, the `episode` page with the listen link, notes, chapters, guests, transcript and comments, `episode_new`), `home_episodes`, `home_podcasts`; list helpers `$pd_*` in `parts.php`; `tests/design_template_podcast_collections_test.php`.

- 4: `mail.php` (the e-mail builders) and `sponsors.php`: `sponsor` (audience, packages, past sponsors' words), the sponsorship enquiry (`sponsor_enquiry`, `enquiry_view`, `my_enquiries`, `staff_enquiries`), the guest pitch (`guest_pitch`, its view and staff list), `privacy`, `email_reply`; test.

- 5: `supporters.php`: `support` (tiers, what supporters get), the supporters' area in the `supporters` folder (`supporters_home`, three bonus episode pages with the static player), and with the shop the tier products (`membership_days`), product, cart, checkout, order, `email_order`, gift memberships; test.

- 6: `standard.php`: contact, inbox, conversation, newsletter, News (the blog), blog post, help search, error ([[design-templates-standard-pages]]); test.

- 7: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.

- 8: the episode page's player bound to the episode's `audio` field (`_bindings.src`, decided A in #7654, `designer/bound-media-src.md`) once that binding lands; the Listen link stays as fallback.

- 9: assembly: `pinegrap-podcast.php` (order, look, fonts, `ppd-` rules, folders `supporters` (membership) and `episodes`, groups, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text), `designer/design-template-podcast.md`; the PR body names `[NEW] Added the Podcast Network design template`; this plan and its design file are deleted.

## Where to start?
- The next free step is the lowest one not marked done. Models: `pinegrap-music/` (collections, filters), `pinegrap-courses/` (membership folder, lesson video), `pinegrap-hotel/` (requests); [[design-templates-file-format]].

## Open questions
- None: the player binding was decided in #7654. Everything else reuses shipped features.
